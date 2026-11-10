# ============================================================
# deploy-smart-v2.ps1 — Deploy ONLY new/modified files using MD5 hashes
#
# Based on deploy-smart.ps1 with 4 professional fixes:
#   [Fix-1] Invalid regex "bootstrap\cache" corrected to "bootstrap/cache"
#           (.NET regex throws on \c escape -> script previously crashed)
#   [Fix-2] Failed uploads are tracked and their hashes are NOT saved,
#           so failed files are retried automatically on the next run
#           (previously a FAILED upload was recorded as deployed forever)
#   [Fix-3] Automatic deletions are guarded:
#           -DryRun            -> show the full plan, change nothing
#           default            -> interactive confirmation before deleting
#           -ConfirmDeletes    -> skip the prompt and delete directly
#   [Fix-4] "public/storage" excluded from the scan so uploaded media /
#           storage content is never pushed to the server by mistake
#
# Run (preview only):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v2.ps1 -DryRun
# Run (real deploy):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v2.ps1
# Run (real deploy, auto-confirm deletions):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v2.ps1 -ConfirmDeletes
# ============================================================
param(
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_43358452",
    [string]$FtpPass = "yesX407zIalBpp",
    [string]$RemoteRoot = "/htdocs",

    # عرض الخطة فقط (رفع/حذف) بدون تنفيذ أي عملية على السيرفر
    [switch]$DryRun,

    # تنفيذ الحذف مباشرة دون سؤال (استخدمها بحذر)
    [switch]$ConfirmDeletes
)

$ErrorActionPreference = "Stop"
$Source = Split-Path -Parent $PSScriptRoot
$HashFile = Join-Path $Source ".deploy_hashes.json"

$FoldersToScan = @("app", "resources", "public", "routes", "config", "database")

# ملاحظة [Fix-1]: جميع الأنماط تُقارن ضد مسار موحّد بشرطات أمامية (/)
# لذلك "bootstrap/cache" صحيح، بينما "bootstrap\cache" كان يكسر المحرك
# ملاحظة [Fix-4]: أُضيف "public/storage" لحماية ملفات التخزين/الوسائط
$IgnoreList = @(
    '\.git(/|$)',
    'node_modules(/|$)',
    'vendor(/|$)',
    '^storage(/|$)',
    'public/storage(/|$)',
    '\.env($|\.)',
    '\.deploy_hashes',
    'bootstrap/cache(/|$)'
)

# ملفات قديمة بأسماء خاطئة (حالة أحرف) يجب حذفها من السيرفر دائماً
$LegacyFilesToDelete = @(
    "app/Models/Inventory/unit.php",
    "app/Models/Accounting/openingBalance.php",
    "app/Http/Resources/sales/SalesInvoiceResource.php",
    "app/Http/Resources/sales/SalesInvoiceDetailResource.php",
    "app/Http/Resources/sales/SalesReturnResource.php",
    "app/Http/Resources/sales/SalesReturnDetailResource.php",
    "app/Http/Resources/purchases/PurchaseInvoiceResource.php",
    "app/Http/Resources/purchases/PurchaseInvoiceDetailResource.php",
    "app/Http/Resources/purchases/PurchaseReturnResource.php",
    "app/Http/Resources/purchases/PurchaseReturnDetailResource.php"
)

Write-Host "Scanning files and calculating hashes (MD5)..." -ForegroundColor Cyan

# ═══════════════════════════════
# 1) الفحص المحلي وحساب البصمات
# ═══════════════════════════════
$NewHashes = @{}
foreach ($folder in $FoldersToScan) {
    $folderPath = Join-Path $Source $folder
    if (-not (Test-Path $folderPath)) { continue }

    $files = Get-ChildItem $folderPath -Recurse -File
    foreach ($file in $files) {
        $relPath = $file.FullName.Substring($Source.Length + 1) -replace '\\', '/'

        $skip = $false
        foreach ($ignore in $IgnoreList) {
            if ($relPath -match $ignore) { $skip = $true; break }
        }
        if ($skip) { continue }

        $NewHashes[$relPath] = (Get-FileHash $file.FullName -Algorithm MD5).Hash
    }
}

# قراءة البصمات القديمة (من آخر عملية نشر ناجحة)
$OldHashes = @{}
if (Test-Path $HashFile) {
    $json = Get-Content $HashFile -Raw | ConvertFrom-Json
    foreach ($prop in $json.PSObject.Properties) {
        $OldHashes[$prop.Name] = $prop.Value
    }
}

# ═══════════════════════════════
# 2) دوال FTP
# ═══════════════════════════════
function New-FtpRequest([string]$uri, [string]$method) {
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Method = $method
    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.Timeout = 90000
    return $req
}

# جلب حجم الملف من السيرفر (-1 = غير موجود أو فشل الاتصال)
function Get-RemoteFileSize([string]$rel) {
    $remotePath = "$RemoteRoot/$rel"
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remotePath" ([System.Net.WebRequestMethods+Ftp]::GetFileSize)
        $resp = $req.GetResponse()
        $size = $resp.ContentLength
        $resp.Close()
        return $size
    }
    catch {
        return -1
    }
}

function New-RemoteDirectory([string]$relDir) {
    $parts = $relDir -split '[/\\]' | Where-Object { $_ }
    $cur = $RemoteRoot
    foreach ($p in $parts) {
        $cur = "$cur/$p"
        try {
            $req = New-FtpRequest "ftp://$FtpHost$cur" ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
            $resp = $req.GetResponse(); $resp.Close()
        }
        catch { }
    }
}

# ملاحظة [Fix-2]: الدالة تعيد $true/$false بدل ابتلاع الفشل
function Send-FtpFile([string]$rel) {
    $localPath = Join-Path $Source $rel
    if (-not (Test-Path $localPath)) { Write-Host "Not found: $rel" -ForegroundColor Red; return $false }

    $remotePath = "$RemoteRoot/" + ($rel -replace '\\', '/')
    $remoteDir = $remotePath -replace '/[^/]+$', ''
    New-RemoteDirectory ($remoteDir.Substring($RemoteRoot.Length).TrimStart('/'))

    Write-Host "Uploading: $rel ... " -NoNewline
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remotePath" ([System.Net.WebRequestMethods+Ftp]::UploadFile)
        $bytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $bytes.Length
        $s = $req.GetRequestStream(); $s.Write($bytes, 0, $bytes.Length); $s.Close()
        $resp = $req.GetResponse(); $resp.Close()
        Write-Host "OK" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "FAILED" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
        return $false
    }
}

function Remove-FtpFile([string]$rel) {
    $remotePath = "$RemoteRoot/$rel"
    Write-Host "Deleting: $rel ... " -NoNewline
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remotePath" ([System.Net.WebRequestMethods+Ftp]::DeleteFile)
        $resp = $req.GetResponse(); $resp.Close()
        Write-Host "OK" -ForegroundColor Green
    }
    catch {
        Write-Host "Skipped (might not exist)" -ForegroundColor Gray
    }
}

# مسح كاش الواجهات: حذف ملفات .php من storage/framework/views على السيرفر
# كل العمليات داخل try/catch حتى لا ينهار السكربت مهما حدث
function Clear-RemoteViewCache {
    $remoteDir = "$RemoteRoot/storage/framework/views"
    $viewFiles = @()
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remoteDir" ([System.Net.WebRequestMethods+Ftp]::ListDirectory)
        $resp = $req.GetResponse()
        $stream = $resp.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        while ($null -ne ($line = $reader.ReadLine())) {
            $name = $line.Trim()
            if ($name -and $name.ToLower().EndsWith('.php')) { $viewFiles += $name }
        }
        $reader.Close(); $stream.Close(); $resp.Close()
    }
    catch {
        Write-Host "Could not list view cache directory: $($_.Exception.Message)" -ForegroundColor Yellow
        return $false
    }

    if ($viewFiles.Count -eq 0) {
        Write-Host "View cache is already empty (no .php files found)." -ForegroundColor DarkCyan
        return $true
    }

    Write-Host "Found $($viewFiles.Count) cached view file(s). Clearing..." -ForegroundColor Cyan
    #$failures = 0
    foreach ($name in $viewFiles) {
        Remove-FtpFile "storage/framework/views/$name"
    }
    return $true
}

# ═══════════════════════════════
# 3) تحديد ما يجب رفعه وحذفه
# ═══════════════════════════════
$FilesToUpload = @()
$FilesToDelete = @()

foreach ($relPath in $NewHashes.Keys) {
    if (-not $OldHashes.ContainsKey($relPath)) {
        # ملف جديد: تحقق إن كان موجوداً على السيرفر بنفس الحجم (أول نشر على نسخة قائمة)
        $localSize = (Get-Item (Join-Path $Source $relPath)).Length
        Write-Host "Checking remote status: $relPath ... " -NoNewline
        $remoteSize = Get-RemoteFileSize $relPath

        if ($remoteSize -eq $localSize) {
            Write-Host "Skipped (already exists, size matches)" -ForegroundColor DarkCyan
        }
        else {
            Write-Host "Needs Upload" -ForegroundColor Yellow
            $FilesToUpload += $relPath
        }
    }
    elseif ($OldHashes[$relPath] -ne $NewHashes[$relPath]) {
        # ملف معدّل محلياً
        $FilesToUpload += $relPath
    }
}

# ملفات حُذفت محلياً (موجودة في السجل القديم ولم تعد في المسح الحالي)
foreach ($relPath in $OldHashes.Keys) {
    if (-not $NewHashes.ContainsKey($relPath)) {
        $FilesToDelete += $relPath
    }
}

$FilesToDelete = ($FilesToDelete + $LegacyFilesToDelete) | Select-Object -Unique

# ═══════════════════════════════
# 4) عرض الخطة (يعمل في DryRun وأيضاً قبل التنفيذ)
# ═══════════════════════════════
Write-Host ""
Write-Host "=== Deployment Plan ===" -ForegroundColor Cyan
Write-Host ("  Files to upload : {0}" -f $FilesToUpload.Count)
Write-Host ("  Files to delete : {0}" -f $FilesToDelete.Count)
Write-Host ("  Mode            : {0}" -f ($(if ($DryRun) { 'DRY RUN (no changes will be made)' } else { 'LIVE' }))) -ForegroundColor $(if ($DryRun) { 'Yellow' } else { 'Green' })

if ($FilesToDelete.Count -gt 0) {
    Write-Host "`n  -- Deletions --" -ForegroundColor DarkYellow
    $FilesToDelete | ForEach-Object { Write-Host "    [DEL] $_" -ForegroundColor DarkYellow }
}

if ($DryRun) {
    if ($FilesToUpload.Count -gt 0) {
        Write-Host "`n  -- Uploads --" -ForegroundColor DarkCyan
        $FilesToUpload | ForEach-Object { Write-Host "    [UP ] $_" -ForegroundColor DarkCyan }
    }
    Write-Host "  -- Preview only -- view cache clearance step will be offered interactively on a real run (not executed in DryRun)." -ForegroundColor DarkCyan
    Write-Host "`n=== DRY RUN FINISHED — nothing was changed ===" -ForegroundColor Yellow
    exit 0
}

# ═══════════════════════════════
# 5) الحذف — ملاحظة [Fix-3]: حارس قبل التنفيذ
# ═══════════════════════════════
$DoDeletes = $false
if ($FilesToDelete.Count -gt 0) {
    if ($ConfirmDeletes) {
        $DoDeletes = $true
    }
    else {
        $answer = Read-Host ("Confirm deletion of {0} file(s) from the server? (y/N)" -f $FilesToDelete.Count)
        if ($answer -match '^(y|yes)$') { $DoDeletes = $true }
        else { Write-Host "Deletions cancelled by user." -ForegroundColor Yellow }
    }
}

if ($DoDeletes) {
    Write-Host "`n=== Deleting Old/Removed Files ===" -ForegroundColor Cyan
    foreach ($f in $FilesToDelete) { Remove-FtpFile $f }
}

# ═══════════════════════════════
# 6) الرفع الفعلي
# ═══════════════════════════════
$FailedUploads = @()
if ($FilesToUpload.Count -eq 0) {
    Write-Host "`nNo new or modified files to upload. Everything is up to date!" -ForegroundColor Green
}
else {
    Write-Host "`nFound $($FilesToUpload.Count) files to upload." -ForegroundColor Cyan
    Write-Host "=== Starting Upload ===" -ForegroundColor Cyan
    foreach ($f in $FilesToUpload) {
        $ok = Send-FtpFile $f
        if (-not $ok) { $FailedUploads += $f }
    }
}

# ═══════════════════════════════
# 7) حفظ البصمات — ملاحظة [Fix-2]:
#    بصمات الملفات الفاشلة لا تُحفظ، فتُعاد المحاولة تلقائياً في التشغيل القادم
# ═══════════════════════════════
$SavedHashes = @{}
foreach ($relPath in $NewHashes.Keys) {
    if ($FailedUploads -notcontains $relPath) { $SavedHashes[$relPath] = $NewHashes[$relPath] }
}

$SavedHashes | ConvertTo-Json | Out-File $HashFile -Encoding utf8

Write-Host ""
if ($FailedUploads.Count -gt 0) {
    Write-Host ("WARNING: {0} file(s) failed to upload — their hashes were NOT saved, they will be retried next run:" -f $FailedUploads.Count) -ForegroundColor Red
    $FailedUploads | ForEach-Object { Write-Host "    [FAIL] $_" -ForegroundColor Red }
}

# ═══════════════════════════════
# 8) مسح كاش الواجهات (سؤال تفاعلي في نهاية السكربت)
#    - لا يظهر في وضع -DryRun (لأن DryRun يخرج قبل هذه الخطوة)
#    - رفض العملية (N/Enter/أي شيء آخر) لا يوقف السكربت — يتجاوز الخطوة ويكمل إلى DONE
# ═══════════════════════════════
$clearCache = Read-Host "Do you want to clear the view cache (storage/framework/views)? (y/N)"
if ($clearCache -match '^(y|yes)$') {
    $result = Clear-RemoteViewCache
    if ($result) { Write-Host "View cache cleared successfully!" -ForegroundColor Green }
    else { Write-Host "View cache clearance failed — continuing anyway..." -ForegroundColor Yellow }
}
else {
    Write-Host "Skipping view cache clearance. Continuing..." -ForegroundColor Yellow
}

Write-Host "=== Updated deployment tracker (.deploy_hashes.json) ===" -ForegroundColor DarkGray
Write-Host "=== DONE ===" -ForegroundColor Cyan
