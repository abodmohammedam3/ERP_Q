# File: scripts/clear-cache.ps1
# ============================================================
# clear-cache.ps1 - Clear Laravel caches on the remote server only
# (no uploads). Useful after config / blade changes on InfinityFree
# where there is no SSH access to run artisan commands.
#
# Targets (all under /htdocs/storage/framework/):
#   - views    : compiled blade files (*.php)   -> default
#   - config   : cached config packages         -> only with -All
#   - sessions : session files                  -> only with -All
#   - cache    : framework cache data files     -> only with -All
#
# IMPORTANT: sessions/cache/config clearing logs users out and may
# slow the first request afterwards. Views-only (default) is safe.
#
# Run (preview only - lists what would be deleted, changes nothing):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\clear-cache.ps1 -DryRun
# Run (clear compiled views only - safe):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\clear-cache.ps1
# Run (clear views + config + sessions + cache - heavier impact):
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\clear-cache.ps1 -All
# ============================================================
param(
    [string]$FtpHost = "ftpupload.net",
    [string]$FtpUser = "if0_43358452",
    [string]$FtpPass = "yesX407zIalBpp",
    [string]$RemoteRoot = "/htdocs",

    # Clear config + sessions + cache too (heavier impact)
    [switch]$All,

    # Preview only - list what would be deleted, change nothing
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

# ═══════════════════════════════
# FTP helpers (self-contained: this script does not need any other file)
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

# List entries of a remote directory; returns plain file/dir names
# (equivalent of unix "ls -l | cut"): each line contains either a
# full path or just the name depending on the FTP server response.
function Get-RemoteListing([string]$relDir) {
    $remoteDir = "$RemoteRoot/$relDir"
    $names = @()
    try {
        $req = New-FtpRequest "ftp://$FtpHost$remoteDir" ([System.Net.WebRequestMethods+Ftp]::ListDirectory)
        $resp = $req.GetResponse()
        $stream = $resp.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        while ($null -ne ($line = $reader.ReadLine())) {
            $name = $line.Trim()
            if ($name) { $names += $name }
        }
        $reader.Close(); $stream.Close(); $resp.Close()
    }
    catch {
        Write-Host "Could not list '$relDir': $($_.Exception.Message)" -ForegroundColor Yellow
        return @()
    }
    return $names
}

# Normalize an entry to a bare file name:
# FTP LIST lines may be full paths ("/htdocs/storage/.../file.php")
# or bare names; Split-Path -Leaf gives us the bare name in both
# cases (and also strips leading slashes safely).
function Get-CleanName([string]$name) {
    $clean = $name.Trim()
    $clean = $clean -replace '^/', ''
    $clean = Split-Path $clean -Leaf
    return $clean
}

# Clear all *.php (or all files) inside a remote directory
function Clear-RemoteDir([string]$relDir, [string]$extensionFilter, [switch]$DryRun) {
    $raw = Get-RemoteListing $relDir
    if ($raw.Count -eq 0) {
        Write-Host "[$relDir] nothing to clear (empty or unreachable)." -ForegroundColor DarkCyan
        return
    }

    $targets = @()
    foreach ($name in $raw) {
        $clean = Get-CleanName $name
        if (-not $clean) { continue }
        if ($extensionFilter -eq '*' -or $clean.ToLower().EndsWith($extensionFilter)) {
            $targets += $clean
        }
    }

    if ($targets.Count -eq 0) {
        Write-Host "[$relDir] nothing matching '$extensionFilter' found." -ForegroundColor DarkCyan
        return
    }

    Write-Host "[$relDir] found $($targets.Count) item(s):" -ForegroundColor Cyan

    if ($DryRun) {
        $targets | ForEach-Object { Write-Host "    [WOULD DEL] $_" -ForegroundColor DarkYellow }
        return
    }

    foreach ($name in $targets) {
        Remove-FtpFile "$relDir/$name"
    }
}

# ═══════════════════════════════
# Main
# ═══════════════════════════════
$modeText = if ($DryRun) { 'DRY RUN (no changes will be made)' } else { 'LIVE' }
$modeColor = if ($DryRun) { 'Yellow' } else { 'Green' }

Write-Host "=== Remote Cache Clearance Plan ===" -ForegroundColor Cyan
Write-Host ("  Host     : {0}" -f $FtpHost)
Write-Host ("  Root     : {0}" -f $RemoteRoot)
Write-Host ("  Scope    : {0}" -f ($(if ($All) { 'views + config + sessions + cache' } else { 'views only (safe)' })))
Write-Host ("  Mode     : {0}" -f $modeText) -ForegroundColor $modeColor

# 1) Compiled blade views - always cleared (safe, forces recompile)
Clear-RemoteDir -relDir "storage/framework/views" -extensionFilter '.php' -DryRun:$DryRun

# 2) With -All: config cache, sessions, framework cache data
if ($All) {
    Clear-RemoteDir -relDir "storage/framework/config" -extensionFilter '.php' -DryRun:$DryRun
    Clear-RemoteDir -relDir "storage/framework/sessions" -extensionFilter '*' -DryRun:$DryRun
    Clear-RemoteDir -relDir "storage/framework/cache/data" -extensionFilter '*' -DryRun:$DryRun
}

if ($DryRun) {
    Write-Host "`n=== DRY RUN FINISHED - nothing was changed ===" -ForegroundColor Yellow
    exit 0
}

Write-Host "`n=== Cache clearance DONE ===" -ForegroundColor Cyan
Write-Host "Tip: reload the site - first request may be slower while views/config rebuild." -ForegroundColor DarkGray
