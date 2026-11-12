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

# قائمة ملفات resources المعدلة فقط (تسمية الرعوي + اسم النظام في الناف بار)
# ملاحظة: ملفا التقارير المعدّلان (PurchaseInvoicesReport / AccountStatementReport)
# يُرفعان تلقائياً مع مجلد app الكامل أدناه
$ModifiedFiles = @(
    "resources\views\layouts\navbar.blade.php",
    "resources\views\layouts\sidebar.blade.php",
    "resources\views\dashboard\SupplyDistribution.blade.php",
    "resources\views\dashboard\SystemIndicators.blade.php",
    "resources\views\operation\accounting\paymentVouchers\accountPickerModal.blade.php",
    "resources\views\operation\model\suppliers.blade.php",
    "resources\views\operation\models\suppliers.blade.php",
    "resources\views\operation\movements\balances\modals.blade.php",
    "resources\views\operation\movements\balances\table.blade.php",
    "resources\views\operation\movements\models\suppliers.blade.php",
    "resources\views\operation\purchase-distribution\history\invoice-picker-modal.blade.php",
    "resources\views\operation\purchase-distribution\accountPickerModal.blade.php",
    "resources\views\operation\purchase-distribution\invoice-modal.blade.php",
    "resources\views\operation\purchases\invoicesPurch\haed.blade.php",
    "resources\views\operation\purchases\invoicesPurch\index.blade.php",
    "resources\views\operation\purchases\returns\header.blade.php",
    "resources\views\operation\purchases\returns\index.blade.php",
    "resources\views\operation\sales\invoices\detalis.blade.php",
    "resources\views\print\purchase-invoice.blade.php",
    "resources\views\print\purchase-return.blade.php",
    "resources\views\setting\accounting\openingBalances\accountPicker.blade.php",
    "resources\views\setting\accounting\openingBalances\index.blade.php",
    "resources\views\setting\accounting\openingBalances\tabs.blade.php",
    "resources\views\setting\suppliers\addUpdate.blade.php",
    "resources\views\setting\suppliers\deletModel.blade.php",
    "resources\views\setting\suppliers\index.blade.php",
    "resources\views\setting\suppliers\print.blade.php",
    "resources\views\setting\suppliers\search.blade.php",
    "resources\views\setting\suppliers\table.blade.php",
    "resources\views\shared\lookup-modal.blade.php",
    "resources\js\accounting\chartOfAccounts.js",
    "resources\js\accounting\openingBalances.js",
    "resources\js\invoice\sales_invoice.js",
    "resources\js\returns\purchase_return.js",
    "resources\js\purchases.js",
    "resources\js\shared\lookup.js",
    "resources\js\shared\supplier.js",
    "resources\js\supplier.js"
)

# إضافة كل ملفات مجلد public/build
$BuildFiles = Get-ChildItem "$Source\public\build" -Recurse -File | ForEach-Object {
    $_.FullName.Substring($Source.Length + 1)
}

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
# رفع مجلد app بالكامل + ملفات resources المعدلة + مجلد public/build
# مع استبدال أي ملفات قديمة على السيرفر
# ═══════════════════════════════

$AppDir = Join-Path $Source 'app'
$AllAppFiles = Get-ChildItem $AppDir -Recurse -File | ForEach-Object {
    $_.FullName.Substring($Source.Length + 1)
}

$FilesToUpload = $AllAppFiles + $BuildFiles + $ModifiedFiles

Write-Host "Found $($FilesToUpload.Count) files (app full + public/build + modified resources)." -ForegroundColor Cyan

Write-Host "=== Starting Upload (app FULL + build + modified resources) ===" -ForegroundColor Cyan
foreach ($f in $FilesToUpload) {
    Send-FtpFile $f
}

Write-Host "`n=== Deleting Old Misnamed Files (Case Sensitivity) ===" -ForegroundColor Cyan
foreach ($f in $FilesToDelete) {
    Remove-FtpFile $f
}

Write-Host "`n=== DONE ===" -ForegroundColor Cyan