# Configure Spotweb on XAMPP Apache (Windows) v1.0.1
# ASCII-only for Windows PowerShell 5.1
<#
.SYNOPSIS
  Install/configure XAMPP Apache to serve an existing Spotweb install.

.DESCRIPTION
  - Optionally installs XAMPP 8.2 via winget (ApacheFriends.Xampp.8.2)
  - Enables rewrite/headers/expires/deflate
  - Creates an Apache vhost for Spotweb
  - Enables common PHP extensions in XAMPP php.ini
  - Restarts Apache

  Keeps your existing Spotweb DB settings (e.g. MariaDB on 127.0.0.1).
  Stop php -S on port 9999 before using Apache on port 80.

.EXAMPLE
  # Run elevated PowerShell (Run as administrator)
  Set-ExecutionPolicy -Scope Process Bypass
  .\Configure-Spotweb-Xampp.ps1 -SpotwebDir C:\Users\admin\Spotweb
#>
[CmdletBinding()]
param(
  [string]$SpotwebDir = (Join-Path $env:USERPROFILE 'Spotweb'),
  [string]$XamppDir = 'C:\xampp',
  [string]$ServerName = 'spotweb.local',
  [int]$Port = 80,
  [switch]$InstallXampp,
  [switch]$SkipApacheRestart
)

$ErrorActionPreference = 'Stop'

function Write-Info([string]$Message) { Write-Host "i  $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "OK $Message" -ForegroundColor Green }
function Write-WarnMsg([string]$Message) { Write-Host "!  $Message" -ForegroundColor Yellow }
function Die([string]$Message) { Write-Host "X  $Message" -ForegroundColor Red; exit 1 }

function Test-IsAdmin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  return $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Enable-HttpdModule {
  param([string]$HttpdConf, [string]$ModuleToken)
  # ModuleToken example: rewrite_module modules/mod_rewrite.so
  $raw = Get-Content -LiteralPath $HttpdConf -Raw
  $pattern = "(?im)^\s*#\s*LoadModule\s+$([regex]::Escape(($ModuleToken -split '\s+')[0]))\s+"
  if ($raw -match "(?im)^\s*LoadModule\s+$([regex]::Escape(($ModuleToken -split '\s+')[0]))\s+") {
    Write-Ok ("Already enabled: " + ($ModuleToken -split '\s+')[0])
    return
  }
  if ($raw -match $pattern) {
    $raw = [regex]::Replace($raw, $pattern, { param($m) ($m.Value -replace '^\s*#\s*', '') }, 1)
    Set-Content -LiteralPath $HttpdConf -Value $raw -Encoding ASCII
    Write-Ok ("Enabled LoadModule " + ($ModuleToken -split '\s+')[0])
    return
  }
  # Append if missing entirely
  Add-Content -LiteralPath $HttpdConf -Value "`r`nLoadModule $ModuleToken`r`n" -Encoding ASCII
  Write-Ok ("Appended LoadModule $ModuleToken")
}

function Ensure-IncludeVhosts([string]$HttpdConf) {
  $raw = Get-Content -LiteralPath $HttpdConf -Raw
  if ($raw -match '(?im)^\s*Include\s+conf/extra/httpd-vhosts\.conf') {
    Write-Ok "httpd-vhosts.conf already included"
    return
  }
  if ($raw -match '(?im)^\s*#\s*Include\s+conf/extra/httpd-vhosts\.conf') {
    $raw = [regex]::Replace($raw, '(?im)^\s*#\s*Include\s+conf/extra/httpd-vhosts\.conf\s*$', 'Include conf/extra/httpd-vhosts.conf', 1)
    Set-Content -LiteralPath $HttpdConf -Value $raw -Encoding ASCII
    Write-Ok "Uncommented Include conf/extra/httpd-vhosts.conf"
    return
  }
  Add-Content -LiteralPath $HttpdConf -Value "`r`nInclude conf/extra/httpd-vhosts.conf`r`n" -Encoding ASCII
  Write-Ok "Added Include conf/extra/httpd-vhosts.conf"
}

function Set-PhpExtension {
  param([string]$PhpIni, [string]$ExtName, [string]$ExtDir)
  $dll = Join-Path $ExtDir ("php_$ExtName.dll")
  if (-not (Test-Path -LiteralPath $dll)) {
    Write-WarnMsg "Skipping $ExtName (missing php_$ExtName.dll)"
    return
  }
  $raw = Get-Content -LiteralPath $PhpIni -Raw
  $raw = [regex]::Replace($raw, "(?im)^\s*;\s*extension\s*=\s*(php_)?$ExtName(\.dll)?\s*$", "extension=$ExtName")
  if ($raw -notmatch "(?im)^\s*extension\s*=\s*(php_)?$ExtName(\.dll)?\s*$") {
    $raw += "`r`nextension=$ExtName"
  }
  Set-Content -LiteralPath $PhpIni -Value $raw -Encoding ASCII
}

# ---------------- main ----------------
Write-Host ""
Write-Host "Configure Spotweb on XAMPP Apache" -ForegroundColor Green
Write-Host "================================="
Write-Host ""

if (-not (Test-IsAdmin)) {
  Write-WarnMsg "Not running elevated. Apache service restart / XAMPP install may fail."
  Write-WarnMsg "Right-click PowerShell -> Run as administrator, then re-run this script."
}

if (-not (Test-Path -LiteralPath (Join-Path $SpotwebDir 'index.php'))) {
  Die "Spotweb not found at $SpotwebDir (missing index.php)"
}
$SpotwebDir = (Resolve-Path -LiteralPath $SpotwebDir).Path
$SpotwebDirApache = ($SpotwebDir -replace '\\', '/')

if ($InstallXampp -or -not (Test-Path -LiteralPath (Join-Path $XamppDir 'apache\bin\httpd.exe'))) {
  Write-Info "Installing XAMPP 8.2 via winget (ApacheFriends.Xampp.8.2)..."
  if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
    Die "winget not found. Install XAMPP manually from https://www.apachefriends.org/ then re-run."
  }
  & winget install --id ApacheFriends.Xampp.8.2 -e --accept-package-agreements --accept-source-agreements
  if ($LASTEXITCODE -ne 0) {
    Write-WarnMsg "winget XAMPP install returned $LASTEXITCODE (may still be installed)."
  }
  if (-not (Test-Path -LiteralPath (Join-Path $XamppDir 'apache\bin\httpd.exe'))) {
    Die "XAMPP Apache not found at $XamppDir. Pass -XamppDir if installed elsewhere."
  }
  Write-Ok "XAMPP found at $XamppDir"
}

$httpd = Join-Path $XamppDir 'apache\bin\httpd.exe'
$httpdConf = Join-Path $XamppDir 'apache\conf\httpd.conf'
$vhostsConf = Join-Path $XamppDir 'apache\conf\extra\httpd-vhosts.conf'
$phpIni = Join-Path $XamppDir 'php\php.ini'
$phpExt = Join-Path $XamppDir 'php\ext'

foreach ($p in @($httpd, $httpdConf, $vhostsConf)) {
  if (-not (Test-Path -LiteralPath $p)) { Die "Missing XAMPP file: $p" }
}

Write-Info "Enabling Apache modules..."
Enable-HttpdModule -HttpdConf $httpdConf -ModuleToken 'rewrite_module modules/mod_rewrite.so'
Enable-HttpdModule -HttpdConf $httpdConf -ModuleToken 'headers_module modules/mod_headers.so'
Enable-HttpdModule -HttpdConf $httpdConf -ModuleToken 'expires_module modules/mod_expires.so'
Enable-HttpdModule -HttpdConf $httpdConf -ModuleToken 'deflate_module modules/mod_deflate.so'
Ensure-IncludeVhosts -HttpdConf $httpdConf

# Ensure Listen for chosen port
$confRaw = Get-Content -LiteralPath $httpdConf -Raw
if ($confRaw -notmatch "(?im)^\s*Listen\s+$Port\b") {
  Add-Content -LiteralPath $httpdConf -Value "`r`nListen $Port`r`n" -Encoding ASCII
  Write-Ok "Added Listen $Port"
} else {
  Write-Ok "Listen $Port already present"
}

Write-Info "Writing Spotweb vhost to httpd-vhosts.conf..."
$markerBegin = '# BEGIN SPOTWEB VHOST'
$markerEnd = '# END SPOTWEB VHOST'
$vhostBlock = @"
$markerBegin
<VirtualHost *:$Port>
    ServerName $ServerName
    DocumentRoot "$SpotwebDirApache"
    <Directory "$SpotwebDirApache">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/spotweb-error.log"
    CustomLog "logs/spotweb-access.log" common
</VirtualHost>
$markerEnd
"@

$vRaw = Get-Content -LiteralPath $vhostsConf -Raw
if ($vRaw -match [regex]::Escape($markerBegin)) {
  $vRaw = [regex]::Replace(
    $vRaw,
    [regex]::Escape($markerBegin) + '[\s\S]*?' + [regex]::Escape($markerEnd),
    $vhostBlock.TrimEnd(),
    1
  )
} else {
  $vRaw = $vRaw.TrimEnd() + "`r`n`r`n" + $vhostBlock + "`r`n"
}
Set-Content -LiteralPath $vhostsConf -Value $vRaw -Encoding ASCII
Write-Ok "Spotweb vhost configured for http://${ServerName}:$Port/ -> $SpotwebDir"

# hosts file entry
$hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
try {
  $hostsRaw = Get-Content -LiteralPath $hostsPath -Raw -ErrorAction Stop
  if ($hostsRaw -notmatch "(?im)^\s*127\.0\.0\.1\s+$([regex]::Escape($ServerName))\b") {
    Add-Content -LiteralPath $hostsPath -Value "`r`n127.0.0.1`t$ServerName`r`n" -Encoding ASCII
    Write-Ok "Added hosts entry: 127.0.0.1 $ServerName"
  } else {
    Write-Ok "Hosts entry already exists for $ServerName"
  }
} catch {
  Write-WarnMsg "Could not update hosts file (need Admin): $($_.Exception.Message)"
  Write-WarnMsg "Add manually: 127.0.0.1 $ServerName"
}

# PHP extensions for XAMPP PHP
if (Test-Path -LiteralPath $phpIni) {
  Write-Info "Enabling PHP extensions in XAMPP php.ini..."
  $phpRaw = Get-Content -LiteralPath $phpIni -Raw
  if ($phpRaw -match '(?im)^\s*;?\s*extension_dir\s*=') {
    $phpRaw = [regex]::Replace($phpRaw, '(?im)^\s*;?\s*extension_dir\s*=.*$', 'extension_dir="ext"')
    Set-Content -LiteralPath $phpIni -Value $phpRaw -Encoding ASCII
  }
  foreach ($ext in @('curl','gd','mbstring','mysqli','openssl','pdo_mysql','zip')) {
    Set-PhpExtension -PhpIni $phpIni -ExtName $ext -ExtDir $phpExt
  }
  Write-Ok "XAMPP PHP extensions updated"
} else {
  Write-WarnMsg "XAMPP php.ini not found at $phpIni"
}

# Config test + restart
Write-Info "Testing Apache config..."
# httpd -t writes "Syntax OK" to stderr; don't treat that as a terminating error
$oldEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
$testOut = & cmd /c "`"$httpd`" -t 2>&1"
$testCode = $LASTEXITCODE
$ErrorActionPreference = $oldEap
$testOut | ForEach-Object { Write-Host $_ }
if ($testCode -ne 0) {
  Die "Apache config test failed. Fix errors above, then re-run."
}
Write-Ok "Apache config syntax OK"

if (-not $SkipApacheRestart) {
  Write-Info "Restarting Apache..."
  $apacheSvc = Get-Service -ErrorAction SilentlyContinue | Where-Object {
    $_.Name -match '(?i)apache|xampp' -or $_.DisplayName -match '(?i)Apache|XAMPP'
  } | Select-Object -First 1

  $restarted = $false
  if ($apacheSvc) {
    try {
      Restart-Service -Name $apacheSvc.Name -Force -ErrorAction Stop
      Write-Ok "Restarted service $($apacheSvc.Name)"
      $restarted = $true
    } catch {
      Write-WarnMsg "Service restart failed: $($_.Exception.Message)"
    }
  }

  if (-not $restarted) {
    # XAMPP control helpers
    $xamppStart = Join-Path $XamppDir 'apache_start.bat'
    $xamppStop = Join-Path $XamppDir 'apache_stop.bat'
    if ((Test-Path $xamppStop) -and (Test-Path $xamppStart)) {
      cmd /c "`"$xamppStop`""
      Start-Sleep -Seconds 2
      cmd /c "`"$xamppStart`""
      Write-Ok "Apache restarted via XAMPP bat scripts"
      $restarted = $true
    }
  }

  if (-not $restarted) {
    try {
      & $httpd -k restart 2>&1 | Out-Host
      Write-Ok "Apache restarted via httpd -k restart"
    } catch {
      Write-WarnMsg "Could not restart Apache automatically. Use XAMPP Control Panel -> Apache -> Start/Restart."
    }
  }
}

Write-Host ""
Write-Ok "XAMPP Apache is configured for Spotweb"
Write-Host ""
Write-Host "1) Stop any php -S server on port 9999 (Ctrl+C in that window)"
Write-Host "2) In XAMPP Control Panel, ensure Apache is running"
Write-Host "   (MySQL/MariaDB: keep your existing MariaDB service if Spotweb already uses it)"
Write-Host "3) Open:"
if ($Port -eq 80) {
  Write-Host "   http://$ServerName/"
  Write-Host "   http://127.0.0.1/"
} else {
  Write-Host "   http://${ServerName}:$Port/"
  Write-Host "   http://127.0.0.1:$Port/"
}
Write-Host ""
Write-Host "Login: admin / spotweb (unless you changed it)"
Write-Host ""
Write-WarnMsg "If port 80 is busy (IIS/Skype/etc), re-run with: -Port 8080"
Write-Host ""
