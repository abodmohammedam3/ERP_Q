# File: scripts/deploy-smart-v3.ps1
# ============================================================
# deploy-smart-v3.ps1 — Smart incremental deploy based on v2 with:
#
#   [V3-1] Order changed: UPLOAD FIRST, then DELETE.
#          The site keeps working even if an upload fails mid-run.
#   [V3-2] Extra deletion guard: if more than 20 files are to be
#          deleted, a mandatory Read-Host confirmation is required
#          (even with -ConfirmDeletes).
#   [V3-3] "database/bac" added to IgnoreList (test migration copies
#          are never uploaded).
#   [V3-4] View cache is cleared AUTOMATICALLY (no prompt) when the
#          upload list contains any .blade.php file.
#   [V3-5] Uploads use curl.exe (bundled with Windows 10/11) when
#          available for much higher speed; automatic fallback to
#          FtpWebRequest if curl is missing. Directory creation,
#          size checks and deletions still use FtpWebRequest.
#   [V3-6] Post-upload verification: remote file size is compared to
#          the local size; a mismatch marks the upload as FAILED so
#          its hash is not saved (retried next run).
#
# Run (preview only):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v3.ps1 -DryRun
# Run (real deploy):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v3.ps1
# Run (real deploy, skip the normal deletion prompt):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-smart-v3.ps1 -ConfirmDeletes
# ============================================================
param(
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_43358452",
    [string]$FtpPass = "yesX407zIalBpp",
    [string]$RemoteRoot = "/htdocs",

    # Show the plan (uploads/deletes) without touching the server
    [switch]$DryRun,

    # Skip the normal deletion prompt (the >20 extra guard still applies)
    [switch]$ConfirmDeletes
)

$ErrorActionPreference = "Stop"
$Source = Split-Path -Parent $PSScriptRoot
$HashFile = Join-Path $Source ".deploy_hashes.json"

$FoldersToScan = @("app", "resources", "public", "routes", "config", "database")

# All patterns are matched against a normalized forward-slash path
# (a backslash in a regex pattern breaks the .NET engine: \c escape)
# [V3-3] database/bac excluded: local test-migration copies stay local.
$IgnoreList = @(
    '\.git(/|$)',
    'node_modules(/|$)',
    'vendor(/|$)',
    '^storage(/|$)',
    'public/storage(/|$)',
    '\.env($|\.)',
    '\.deploy_hashes',
    'bootstrap/cache(/|$)',
    'database/bac(/|$)'
)

# Legacy misnamed files (wrong letter case) that must always be
# deleted from the server.
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

# [V3-5] Detect curl.exe once (bundled with Windows 10/11)
$CurlPath = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if ($CurlPath) {
    Write-Host "Uploader engine : curl.exe ($CurlPath)" -ForegroundColor DarkCyan
}
else {
    Write-Host "Uploader engine : FtpWebRequest (curl.exe not found)" -ForegroundColor DarkCyan
}

# ═══════════════════════════════
# 1) Local scan and hash calculation
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

# Read the hashes from the last successful deploy
$OldHashes = @{}
if (Test-Path $HashFile) {
    $json = Get-Content $HashFile -Raw | ConvertFrom-Json
    foreach ($prop in $json.PSObject.Properties) {
        $OldHashes[$prop.Name] = $prop.Value
    }
}

# ═══════════════════════════════
# 2) FTP helpers
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

# Remote file size, or -1 when missing / unreachable
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

# [V3-5] curl-based upload: one process per file (no FtpWebRequest
# round-trip), retries built in. Returns $true/$false.
function Send-FtpFileCurl([string]$rel) {
    $localPath = Join-Path $Source $rel
    $remotePath = "$RemoteRoot/" + ($rel -replace '\\', '/')
    $remoteDir = $remotePath -replace '/[^/]+$', ''
    New-RemoteDirectory ($remoteDir.Substring($RemoteRoot.Length).TrimStart('/'))

    Write-Host "Uploading[curl]: $rel ... " -NoNewline
    try {
        & $CurlPath -sS --connect-timeout 30 --max-time 600 --retry 2 `
            -u "$FtpUser`:$FtpPass" `
            -T "$localPath" `
            "ftp://$FtpHost$remotePath"
        if ($LASTEXITCODE -ne 0) {
            Write-Host "FAILED (curl exit $LASTEXITCODE)" -ForegroundColor Red
            return $false
        }
        Write-Host "OK" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "FAILED" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
        return $false
    }
}

# Classic FtpWebRequest upload (fallback). Returns $true/$false.
function Send-FtpFileNet([string]$rel) {
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

# [V3-6] Upload + verify remote size matches local size.
# A mismatch is treated as a FAILED upload (hash not saved).
function Send-FtpFileVerified([string]$rel) {
    $localSize = (Get-Item (Join-Path $Source $rel)).Length

    if ($CurlPath) { $sent = Send-FtpFileCurl $rel }
    else { $sent = Send-FtpFileNet $rel }

    if (-not $sent) { return $false }

    $remoteSize = Get-RemoteFileSize $rel
    if ($remoteSize -ne $localSize) {
        Write-Host "    VERIFY FAILED (remote=$remoteSize local=$localSize)" -ForegroundColor Red
        return $false
    }
    return $true
}

# List remote .php files in a directory, clear the compiled blade cache
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
    foreach ($name in $viewFiles) {
        # FTP LIST قد يعيد المسار الكامل؛ نأخذ الاسم فقط لتجنب views/views
        $cleanName = Split-Path $name -Leaf
        Remove-FtpFile "storage/framework/views/$cleanName"
    }
    return $true
}

# ═══════════════════════════════
# 3) Decide what to upload and what to delete
# ═══════════════════════════════
$FilesToUpload = @()
$FilesToDelete = @()

foreach ($relPath in $NewHashes.Keys) {
    if (-not $OldHashes.ContainsKey($relPath)) {
        # New file: if a same-size copy already exists remotely,
        # skip it (first sync against an existing server copy).
        $localSize = (Get-Item (Join-Path $Source $relPath)).Length
        $remoteSize = Get-RemoteFileSize $relPath

        if ($remoteSize -eq $localSize) {
            Write-Host "Skipped (exists, size matches): $relPath" -ForegroundColor DarkCyan
        }
        else {
            $FilesToUpload += $relPath
        }
    }
    elseif ($OldHashes[$relPath] -ne $NewHashes[$relPath]) {
        # Modified locally
        $FilesToUpload += $relPath
    }
}

# Files deleted locally (present in the old record, absent from the scan)
foreach ($relPath in $OldHashes.Keys) {
    if (-not $NewHashes.ContainsKey($relPath)) {
        $FilesToDelete += $relPath
    }
}

$FilesToDelete = ($FilesToDelete + $LegacyFilesToDelete) | Select-Object -Unique

# ═══════════════════════════════
# 4) Show the plan
# ═══════════════════════════════
Write-Host ""
Write-Host "=== Deployment Plan ===" -ForegroundColor Cyan
Write-Host ("  Files to upload : {0}" -f $FilesToUpload.Count)
Write-Host ("  Files to delete : {0}" -f $FilesToDelete.Count)
Write-Host ("  Order           : upload first, delete afterwards")
$modeText = if ($DryRun) { 'DRY RUN (no changes will be made)' } else { 'LIVE' }
$modeColor = if ($DryRun) { 'Yellow' } else { 'Green' }
Write-Host ("  Mode            : {0}" -f $modeText) -ForegroundColor $modeColor

if ($FilesToDelete.Count -gt 0) {
    Write-Host "`n  -- Deletions --" -ForegroundColor DarkYellow
    $FilesToDelete | ForEach-Object { Write-Host "    [DEL] $_" -ForegroundColor DarkYellow }
}

if ($DryRun) {
    if ($FilesToUpload.Count -gt 0) {
        Write-Host "`n  -- Uploads --" -ForegroundColor DarkCyan
        $FilesToUpload | ForEach-Object { Write-Host "    [UP ] $_" -ForegroundColor DarkCyan }
        $bladeCount = ($FilesToUpload | Where-Object { $_ -like '*.blade.php' }).Count
        if ($bladeCount -gt 0) {
            Write-Host ("  -- View cache will be cleared automatically after upload ({0} blade file(s) changed) --" -f $bladeCount) -ForegroundColor DarkCyan
        }
    }
    Write-Host "`n=== DRY RUN FINISHED - nothing was changed ===" -ForegroundColor Yellow
    exit 0
}

# ═══════════════════════════════
# 5) [V3-1] UPLOADS FIRST
# ═══════════════════════════════
$FailedUploads = @()
if ($FilesToUpload.Count -eq 0) {
    Write-Host "`nNo new or modified files to upload. Everything is up to date!" -ForegroundColor Green
}
else {
    Write-Host "`nFound $($FilesToUpload.Count) files to upload." -ForegroundColor Cyan
    Write-Host "=== Starting Upload ===" -ForegroundColor Cyan
    foreach ($f in $FilesToUpload) {
        $ok = Send-FtpFileVerified $f
        if (-not $ok) { $FailedUploads += $f }
    }
}

# [V3-4] Automatic view-cache clearance: no prompt, only when a
# blade template is part of this deploy.
$bladeChanged = ($FilesToUpload | Where-Object { $_ -like '*.blade.php' }).Count
if ($bladeChanged -gt 0) {
    Write-Host "`n[V3-4] $bladeChanged blade file(s) uploaded - clearing view cache automatically..." -ForegroundColor Cyan
    $cacheOk = Clear-RemoteViewCache
    if (-not $cacheOk) {
        Write-Host "View cache clearance failed - continuing anyway." -ForegroundColor Yellow
    }
}
else {
    Write-Host "`nNo blade changes - view cache clearance not needed." -ForegroundColor DarkCyan
}

# ═══════════════════════════════
# 6) [V3-1] DELETIONS AFTER UPLOADS
#    [V3-2] Mandatory extra guard when count > 20
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

    # [V3-2] Disaster guard: applies even with -ConfirmDeletes
    if ($DoDeletes -and $FilesToDelete.Count -gt 20) {
        Write-Host ("WARNING: {0} deletions is a LARGE batch." -f $FilesToDelete.Count) -ForegroundColor Red
        $answer2 = Read-Host ("Type YES (uppercase) to really delete {0} file(s), anything else cancels" -f $FilesToDelete.Count)
        if ($answer2 -ceq 'YES') {
            Write-Host "Large-batch deletion confirmed." -ForegroundColor Yellow
        }
        else {
            $DoDeletes = $false
            Write-Host "Large-batch deletion cancelled by user." -ForegroundColor Yellow
        }
    }
}

if ($DoDeletes) {
    Write-Host "`n=== Deleting Old/Removed Files ===" -ForegroundColor Cyan
    foreach ($f in $FilesToDelete) { Remove-FtpFile $f }
}

# ═══════════════════════════════
# 7) Save hashes (failed uploads excluded -> retried next run)
# ═══════════════════════════════
$SavedHashes = @{}
foreach ($relPath in $NewHashes.Keys) {
    if ($FailedUploads -notcontains $relPath) { $SavedHashes[$relPath] = $NewHashes[$relPath] }
}

$SavedHashes | ConvertTo-Json | Out-File $HashFile -Encoding utf8

Write-Host ""
if ($FailedUploads.Count -gt 0) {
    Write-Host ("WARNING: {0} file(s) failed to upload or verify - their hashes were NOT saved, they will be retried next run:" -f $FailedUploads.Count) -ForegroundColor Red
    $FailedUploads | ForEach-Object { Write-Host "    [FAIL] $_" -ForegroundColor Red }
}

Write-Host "=== Updated deployment tracker (.deploy_hashes.json) ===" -ForegroundColor DarkGray
Write-Host "=== DONE ===" -ForegroundColor Cyan
