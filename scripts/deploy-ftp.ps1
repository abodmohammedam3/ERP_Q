# ============================================================
# deploy-modified.ps1 - Upload ONLY modified files to InfinityFree
# Run: powershell -NoProfile -ExecutionPolicy Bypass -File scripts\deploy-ftp.ps1
# ============================================================
param(
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_43358452",
    [string]$FtpPass = "yesX407zIalBpp",
    [string]$RemoteRoot = "/htdocs"
)

$ErrorActionPreference = "Stop"
$Source = Split-Path -Parent $PSScriptRoot

# قائمة الملفات المعدلة فقط
$ModifiedFiles = @(
    "app\Models\Customer.php",
    "app\Models\Supplier.php",
    "app\Models\Inventory\Item.php",
    "app\Models\Accounting\OpeningBalance.php",
    "app\Models\Accounting\JournalEntry.php",
    "app\Models\Accounting\JournalEntryLine.php",
    "app\Reports\Accounting\TrialBalanceReport.php",
    "app\Reports\Accounting\GeneralLedgerReport.php",
    "app\Reports\Accounting\AccountStatementReport.php",
    "app\Http\Requests\Inventory\ItemUpdateRequest.php",
    "app\Services\Export\Exporters\ItemExporter.php",
    "app\Services\Export\Exporters\SupplierExporter.php",
    "app\Http\Requests\Inventory\ItemStoreRequest.php",
    "app\Http\Requests\Purchases\StorePurchaseInvoiceRequest.php",
    "app\Http\Requests\Sales\StoreSalesInvoiceRequest.php",
    "app\Http\Controllers\Operation\Movements\InventoryMovementController.php",
    "resources\views\operation\accounting\journalEntries\detailsModal.blade.php",
    "app\Http\Controllers\accounting\JournalEntryController.php",
    "resources\js\accounting\journalEntries.js",
    "app\Reports\Inventory\ItemLedgerReport.php"
)

# إضافة كل ملفات مجلد public/build
$BuildFiles = Get-ChildItem "$Source\public\build" -Recurse -File | ForEach-Object {
    $_.FullName.Substring($Source.Length + 1)
}

$FilesToUpload = $ModifiedFiles + $BuildFiles

# الملفات التي يجب حذفها من السيرفر
$FilesToDelete = @(
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

function New-FtpRequest([string]$uri, [string]$method) {
    $req = [System.Net.FtpWebRequest]::Create($uri)
    $req.Method = $method
    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.Timeout = 90000 # 90 ثانية
    return $req
}

# تم تغيير الاسم هنا لتجنب التحذير
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

# تم تغيير الاسم هنا لتجنب التحذير
function Send-FtpFile([string]$rel) {
    $localPath = Join-Path $Source $rel
    if (-not (Test-Path $localPath)) { Write-Host "Not found: $rel" -ForegroundColor Red; return }
    
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
    }
    catch {
        Write-Host "FAILED" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

# تم تغيير الاسم هنا لتجنب التحذير
function Remove-FtpFile([string]$rel) {
    $remotePath = "$RemoteRoot/$rel"
    Write-Host "Deleting old file: $rel ... " -NoNewline
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remotePath" ([System.Net.WebRequestMethods+Ftp]::DeleteFile)
        $resp = $req.GetResponse(); $resp.Close()
        Write-Host "OK" -ForegroundColor Green
    }
    catch {
        Write-Host "Skipped (might not exist)" -ForegroundColor Gray
    }
}

# ═══════════════════════════════
# رفع مجلد app بالكامل (كل الملفات والمجلدات الفرعية)
# مع استبدال أي ملفات قديمة على السيرفر
# ═══════════════════════════════

$AppDir = Join-Path $Source 'app'
$AllAppFiles = Get-ChildItem $AppDir -Recurse -File | ForEach-Object {
    $_.FullName.Substring($Source.Length + 1)
}

$FilesToUpload = $AllAppFiles + $BuildFiles

Write-Host "Found $($FilesToUpload.Count) files in app folder (full upload)." -ForegroundColor Cyan

Write-Host "=== Starting Upload of app Folder (FULL) ===" -ForegroundColor Cyan
foreach ($f in $FilesToUpload) {
    Send-FtpFile $f
}

Write-Host "`n=== Deleting Old Misnamed Files (Case Sensitivity) ===" -ForegroundColor Cyan
foreach ($f in $FilesToDelete) {
    Remove-FtpFile $f
}

Write-Host "`n=== DONE ===" -ForegroundColor Cyan