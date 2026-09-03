# Configure Spotweb on IIS + PHP FastCGI (Windows) v1.0.0
# ASCII-only for Windows PowerShell 5.1
<#
.SYNOPSIS
  Configure IIS with PHP FastCGI to serve an existing Spotweb install.

.DESCRIPTION
  - Enables IIS + CGI features via DISM
  - Finds or installs portable PHP (NTS x64) for FastCGI
  - Registers PHP as a FastCGI handler in IIS
  - Creates an IIS site for Spotweb with proper handler mappings
  - Enables required PHP extensions in php.ini
  - Optionally installs URL Rewrite module for clean URLs
  - Starts the IIS site

  Keeps your existing Spotweb DB settings (e.g. MariaDB on 127.0.0.1).
  Stop php -S on port 9999 before using IIS.

.EXAMPLE
  # Run elevated PowerShell (Run as administrator)
  Set-ExecutionPolicy -Scope Process Bypass
  .\Configure-Spotweb-IIS.ps1 -SpotwebDir C:\Users\admin\Spotweb

.EXAMPLE
  # Custom port and site name
  .\Configure-Spotweb-IIS.ps1 -SpotwebDir C:\Spotweb -Port 8080 -SiteName 'spotweb.local'
#>
[CmdletBinding()]
param(
  [string]$SpotwebDir = (Join-Path $env:USERPROFILE 'Spotweb'),
  [string]$SiteName = 'spotweb.local',
  [int]$Port = 80,
  [string]$PhpBin = '',
  [switch]$SkipIISRestart,
  [switch]$Force
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

# Path to appcmd.exe - the IIS CLI management tool
function Get-AppCmd {
  $appcmd = Join-Path $env:SystemRoot 'System32\inetsrv\appcmd.exe'
  if (-not (Test-Path -LiteralPath $appcmd)) {
    Die "appcmd.exe not found at $appcmd. Is IIS installed?"
  }
  return $appcmd
}

function Invoke-AppCmd {
  param([string[]]$AppArgs)
  $appcmd = Get-AppCmd
  $output = & $appcmd @AppArgs 2>&1
  $output | ForEach-Object { Write-Host $_ }
  return $LASTEXITCODE
}

# Find PHP binary: prefer portable SpotwebTools PHP, then system PHP
function Resolve-PhpBin {
  param([string]$Preferred)

  if ($Preferred -and (Test-Path -LiteralPath $Preferred)) {
    return (Resolve-Path -LiteralPath $Preferred).Path
  }

  # Portable PHP installed by Install-Spotweb.ps1
  $portable = Join-Path $env:LOCALAPPDATA 'SpotwebTools\php\php-cgi.exe'
  if (Test-Path -LiteralPath $portable) {
    return $portable
  }

  # Check for php-cgi.exe in common locations
  $candidates = @(
    'C:\php\php-cgi.exe',
    'C:\tools\php\php-cgi.exe',
    (Join-Path $env:ProgramFiles 'PHP\php-cgi.exe'),
    (Join-Path ${env:ProgramFiles(x86)} 'PHP\php-cgi.exe')
  )
  foreach ($c in $candidates) {
    if (Test-Path -LiteralPath $c) { return $c }
  }

  # Fall back to php.exe directory and look for php-cgi.exe alongside
  $phpExe = Get-Command php -ErrorAction SilentlyContinue
  if ($phpExe) {
    $phpDir = Split-Path -Parent $phpExe.Source
    $cgi = Join-Path $phpDir 'php-cgi.exe'
    if (Test-Path -LiteralPath $cgi) { return $cgi }
  }

  return $null
}

# Install portable PHP with CGI binary if not found
function Install-PhpPortableForIIS {
  $targetRoot = Join-Path $env:LOCALAPPDATA 'SpotwebTools\php'
  $phpCgi = Join-Path $targetRoot 'php-cgi.exe'
  if ((-not $Force) -and (Test-Path -LiteralPath $phpCgi)) {
    Write-Ok "Using existing portable PHP: $phpCgi"
    return $phpCgi
  }

  Write-Info "Downloading portable PHP (NTS x64) from windows.php.net..."
  $releasesUrl = 'https://windows.php.net/downloads/releases/'
  try {
    $idx = Invoke-WebRequest -Uri $releasesUrl -UseBasicParsing -TimeoutSec 30
  } catch {
    Die "Could not reach windows.php.net: $($_.Exception.Message)"
  }
  $pattern = 'href="(php-(8\.[3-4]\.\d+)-nts-Win32-vs1[67]-x64\.zip)"'
  $zipMatches = [regex]::Matches($idx.Content, $pattern)
  if ($zipMatches.Count -eq 0) {
    $releasesUrl = 'https://windows.php.net/downloads/releases/archives/'
    $idx = Invoke-WebRequest -Uri $releasesUrl -UseBasicParsing -TimeoutSec 30
    $zipMatches = [regex]::Matches($idx.Content, $pattern)
  }
  if ($zipMatches.Count -eq 0) {
    Die "No suitable PHP NTS x64 zip found on windows.php.net"
  }
  $zipName = $zipMatches[0].Groups[1].Value
  $zipUrl = $releasesUrl + $zipName
  $zipPath = Join-Path $env:TEMP $zipName

  Write-Info "Downloading $zipName..."
  Invoke-WebRequest -Uri $zipUrl -OutFile $zipPath -UseBasicParsing -TimeoutSec 120

  if (Test-Path -LiteralPath $targetRoot) {
    Remove-Item -LiteralPath $targetRoot -Recurse -Force
  }
  New-Item -ItemType Directory -Path $targetRoot -Force | Out-Null
  Write-Info "Extracting to $targetRoot..."
  Expand-Archive -Path $zipPath -DestinationPath $targetRoot -Force
  Remove-Item -LiteralPath $zipPath -Force

  if (-not (Test-Path -LiteralPath $phpCgi)) {
    Die "php-cgi.exe missing after extract to $targetRoot"
  }

  # Create php.ini from php.ini-development
  $ini = Join-Path $targetRoot 'php.ini'
  $iniDev = Join-Path $targetRoot 'php.ini-development'
  if ((-not (Test-Path -LiteralPath $ini)) -and (Test-Path -LiteralPath $iniDev)) {
    Copy-Item -LiteralPath $iniDev -Destination $ini
  }

  Write-Ok "Portable PHP installed: $phpCgi"
  return $phpCgi
}

function Enable-PhpExtensions {
  param([string]$PhpIni)
  if (-not (Test-Path -LiteralPath $PhpIni)) {
    Write-WarnMsg "php.ini not found at $PhpIni"
    return
  }

  $phpDir = Split-Path -Parent $PhpIni
  $extDir = Join-Path $phpDir 'ext'
  $raw = Get-Content -LiteralPath $PhpIni -Raw

  # Set extension_dir
  if ($raw -match '(?im)^\s*;?\s*extension_dir\s*=') {
    $raw = [regex]::Replace($raw, '(?im)^\s*;?\s*extension_dir\s*=.*$', 'extension_dir="ext"')
  } else {
    $raw += "`r`nextension_dir=`"ext`""
  }

  # Set cgi.force_redirect = 0 (required for IIS FastCGI)
  if ($raw -match '(?im)^\s*;?\s*cgi\.force_redirect\s*=') {
    $raw = [regex]::Replace($raw, '(?im)^\s*;?\s*cgi\.force_redirect\s*=.*$', 'cgi.force_redirect=0')
  } else {
    $raw += "`r`ncgi.force_redirect=0"
  }

  # Set cgi.fix_pathinfo = 1 (required for IIS URL rewriting)
  if ($raw -match '(?im)^\s*;?\s*cgi\.fix_pathinfo\s*=') {
    $raw = [regex]::Replace($raw, '(?im)^\s*;?\s*cgi\.fix_pathinfo\s*=.*$', 'cgi.fix_pathinfo=1')
  } else {
    $raw += "`r`ncgi.fix_pathinfo=1"
  }

  # Set fastcgi.impersonate = 1 (IIS uses impersonation)
  if ($raw -match '(?im)^\s*;?\s*fastcgi\.impersonate\s*=') {
    $raw = [regex]::Replace($raw, '(?im)^\s*;?\s*fastcgi\.impersonate\s*=.*$', 'fastcgi.impersonate=1')
  } else {
    $raw += "`r`nfastcgi.impersonate=1"
  }

  # Enable required extensions
  $extensions = @('curl', 'gd', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'zip')
  foreach ($ext in $extensions) {
    $dll = Join-Path $extDir "php_$ext.dll"
    if (-not (Test-Path -LiteralPath $dll)) {
      Write-WarnMsg "Skipping $ext (missing php_$ext.dll)"
      continue
    }
    $raw = [regex]::Replace($raw, "(?im)^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$", "extension=$ext")
    if ($raw -notmatch "(?im)^\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$") {
      $raw += "`r`nextension=$ext"
    }
  }

  Set-Content -LiteralPath $PhpIni -Value $raw -Encoding ASCII
  Write-Ok "PHP extensions enabled in $PhpIni"
}

# Enable IIS Windows features via DISM
function Enable-IISFeatures {
  Write-Info "Checking IIS features..."

  $features = @(
    'IIS-WebServerRole',
    'IIS-WebServer',
    'IIS-CommonHttpFeatures',
    'IIS-HttpErrors',
    'IIS-HttpRedirect',
    'IIS-StaticContent',
    'IIS-DefaultDocument',
    'IIS-DirectoryBrowsing',
    'IIS-HttpLogging',
    'IIS-RequestMonitor',
    'IIS-Security',
    'IIS-RequestFiltering',
    'IIS-CGI',
    'IIS-ISAPIExtensions',
    'IIS-ISAPIFilter'
  )

  $needInstall = $false
  foreach ($feat in $features) {
    $state = (Get-WindowsOptionalFeature -Online -FeatureName $feat -ErrorAction SilentlyContinue)
    if ($state -and $state.State -ne 'Enabled') {
      $needInstall = $true
      break
    }
  }

  if (-not $needInstall) {
    # Double-check IIS is actually present
    $appcmd = Join-Path $env:SystemRoot 'System32\inetsrv\appcmd.exe'
    if (Test-Path -LiteralPath $appcmd) {
      Write-Ok "IIS features already enabled"
      return
    }
    $needInstall = $true
  }

  if ($needInstall) {
    Write-Info "Enabling IIS features via DISM (this may take a minute)..."
    $dismArgs = @('/online', '/enable-feature', '/all', '/norestart')
    foreach ($feat in $features) {
      $dismArgs += "/featurename:$feat"
    }
    $code = Start-Process -FilePath 'dism.exe' -ArgumentList $dismArgs -Wait -NoNewWindow -PassThru
    if ($code.ExitCode -ne 0 -and $code.ExitCode -ne 3010) {
      Die "DISM failed to enable IIS features (exit code $($code.ExitCode)). You may need to reboot."
    }
    Write-Ok "IIS features enabled"
    if ($code.ExitCode -eq 3010) {
      Write-WarnMsg "A reboot is required before IIS is fully available."
      Write-WarnMsg "Reboot, then re-run this script."
      exit 0
    }
  }
}

# Register PHP as a FastCGI process in IIS
function Register-PhpFastCgi {
  param([string]$PhpCgiPath)

  $appcmd = Get-AppCmd

  # Check if FastCGI registration already exists
  $existing = & $appcmd list config /section:system.webServer/fastCgi 2>&1 | Out-String
  if ($existing -match [regex]::Escape($PhpCgiPath)) {
    Write-Ok "FastCGI already registered for $PhpCgiPath"
    return
  }

  Write-Info "Registering PHP FastCGI handler..."
  # Add the FastCGI application
  $fullPath = $PhpCgiPath -replace '\\', '/'
  Invoke-AppCmd @('set', 'config', '/section:system.webServer/fastCgi',
    "/+[fullPath='$fullPath',arguments='',monitorChangesTo='php.ini',activityTimeout='600',requestTimeout='600',instanceMaxRequests='10000']")
  Write-Ok "FastCGI registered: $PhpCgiPath"
}

# Create or update the IIS site and handler mapping
function Set-SpotwebIISite {
  param(
    [string]$SpotwebDir,
    [string]$PhpCgiPath,
    [string]$SiteName,
    [int]$Port
  )

  $appcmd = Get-AppCmd
  $fullPath = $PhpCgiPath -replace '\\', '/'

  # Remove existing site if present
  $existingSite = & $appcmd list site $SiteName 2>&1 | Out-String
  if ($existingSite -match $SiteName) {
    Write-Info "Removing existing IIS site '$SiteName'..."
    Invoke-AppCmd @('delete', 'site', $SiteName)
  }

  # Remove existing app pool if present
  $existingPool = & $appcmd list apppool $SiteName 2>&1 | Out-String
  if ($existingPool -match $SiteName) {
    Write-Info "Removing existing app pool '$SiteName'..."
    Invoke-AppCmd @('delete', 'apppool', $SiteName)
  }

  # Create dedicated app pool
  Write-Info "Creating application pool '$SiteName'..."
  Invoke-AppCmd @('add', 'apppool', "/name:$SiteName", '/managedRuntimeVersion:', '/managedPipelineMode:Classic')
  # Set app pool identity to LocalSystem for file access (Spotweb needs cache/ write)
  Invoke-AppCmd @('set', 'config', "/section:applicationPools", "/[name='$SiteName'].processModel.identityType:LocalSystem")

  # Create the site
  Write-Info "Creating IIS site '$SiteName' on port $Port..."
  $bindings = "http/*:${Port}:"
  Invoke-AppCmd @('add', 'site', "/name:$SiteName", "/physicalPath:$SpotwebDir", "/bindings:$bindings")

  # Assign the app pool to the site
  Invoke-AppCmd @('set', 'app', "$SiteName/", "/applicationPool:$SiteName")

  Write-Ok "IIS site created: $SiteName -> $SpotwebDir (port $Port)"

  # Add PHP handler mapping at the site level
  Write-Info "Configuring PHP handler mapping..."

  # Write web.config in the Spotweb root
  $webConfigPath = Join-Path $SpotwebDir 'web.config'

  # Preserve existing web.config if it has non-Spotweb content
  if (Test-Path -LiteralPath $webConfigPath) {
    $existing = Get-Content -LiteralPath $webConfigPath -Raw
    if ($existing -match 'PHP_via_FastCGI') {
      Write-Ok "web.config already has PHP handler - updating..."
    } else {
      Write-WarnMsg "Existing web.config found - backing up..."
      Copy-Item -LiteralPath $webConfigPath -Destination "$webConfigPath.bak" -Force
    }
  }

  $fullWebConfig = @"
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <handlers>
      <add name="PHP_via_FastCGI" path="*.php" verb="*" modules="FastCgiModule" scriptProcessor="$fullPath" resourceType="File" requireAccess="Script" />
    </handlers>
    <defaultDocument>
      <files>
        <add value="index.php" />
      </files>
    </defaultDocument>
    <staticContent>
      <mimeMap fileExtension=".css" mimeType="text/css" />
      <mimeMap fileExtension=".js" mimeType="application/javascript" />
      <mimeMap fileExtension=".json" mimeType="application/json" />
      <mimeMap fileExtension=".woff" mimeType="font/woff" />
      <mimeMap fileExtension=".woff2" mimeType="font/woff2" />
      <mimeMap fileExtension=".svg" mimeType="image/svg+xml" />
    </staticContent>
    <httpProtocol>
      <customHeaders>
        <add name="X-Content-Type-Options" value="nosniff" />
      </customHeaders>
    </httpProtocol>
  </system.webServer>
</configuration>
"@

  Set-Content -LiteralPath $webConfigPath -Value $fullWebConfig -Encoding UTF8
  Write-Ok "web.config written to $webConfigPath"

  # Set directory permissions for IIS
  Write-Info "Setting directory permissions..."
  $cacheDir = Join-Path $SpotwebDir 'cache'
  if (Test-Path -LiteralPath $cacheDir) {
    try {
      $acl = Get-Acl -LiteralPath $cacheDir
      $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        'IIS_IUSRS', 'Modify', 'ContainerInherit,ObjectInherit', 'None', 'Allow'
      )
      $acl.AddAccessRule($rule)
      Set-Acl -LiteralPath $cacheDir -AclObject $acl
      Write-Ok "Cache directory permissions set for IIS_IUSRS"
    } catch {
      Write-WarnMsg "Could not set cache permissions: $($_.Exception.Message)"
      Write-WarnMsg "Manually grant Modify to IIS_IUSRS on: $cacheDir"
    }
  }

  # Also set permissions on the Spotweb root for IIS_IUSRS (read + execute)
  try {
    $acl = Get-Acl -LiteralPath $SpotwebDir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
      'IIS_IUSRS', 'ReadAndExecute', 'ContainerInherit,ObjectInherit', 'None', 'Allow'
    )
    $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $SpotwebDir -AclObject $acl
    Write-Ok "Spotweb root permissions set for IIS_IUSRS"
  } catch {
    Write-WarnMsg "Could not set root permissions: $($_.Exception.Message)"
  }
}

# Optionally install IIS URL Rewrite module
function Install-UrlRewriteModule {
  Write-Info "Checking for IIS URL Rewrite module..."

  $appcmd = Get-AppCmd
  & $appcmd list config /section:system.webServer/rewrite 2>&1 | Out-Null
  if ($LASTEXITCODE -eq 0) {
    Write-Ok "URL Rewrite module is available"
    return $true
  }

  Write-Info "URL Rewrite module not found. Installing from Microsoft..."

  # Download URL Rewrite 2.1
  $rewriteUrl = 'https://download.microsoft.com/download/1/2/5/125F9AF0-E1A0-4B83-8F2F-9B7B4E1F8D1F/rewrite_amd64_en-US.msi'
  $msiPath = Join-Path $env:TEMP 'rewrite_amd64.msi'

  try {
    Invoke-WebRequest -Uri $rewriteUrl -OutFile $msiPath -UseBasicParsing -TimeoutSec 120
  } catch {
    Write-WarnMsg "Could not download URL Rewrite module: $($_.Exception.Message)"
    Write-WarnMsg "Install manually from: https://www.iis.net/downloads/microsoft/url-rewrite"
    return $false
  }

  Write-Info "Installing URL Rewrite module..."
  $msiCode = Start-Process -FilePath 'msiexec.exe' -ArgumentList @('/i', $msiPath, '/quiet', '/norestart') -Wait -NoNewWindow -PassThru
  Remove-Item -LiteralPath $msiPath -Force -ErrorAction SilentlyContinue

  if ($msiCode.ExitCode -eq 0) {
    Write-Ok "URL Rewrite module installed"
    return $true
  } else {
    Write-WarnMsg "URL Rewrite install returned exit code $($msiCode.ExitCode)"
    Write-WarnMsg "Install manually from: https://www.iis.net/downloads/microsoft/url-rewrite"
    return $false
  }
}

# ---------------- main ----------------
Write-Host ""
Write-Host "Configure Spotweb on IIS + PHP FastCGI" -ForegroundColor Green
Write-Host "======================================="
Write-Host ""

if (-not (Test-IsAdmin)) {
  Die "This script requires Administrator privileges. Right-click PowerShell -> Run as administrator."
}

if (-not (Test-Path -LiteralPath (Join-Path $SpotwebDir 'index.php'))) {
  Die "Spotweb not found at $SpotwebDir (missing index.php)"
}
$SpotwebDir = (Resolve-Path -LiteralPath $SpotwebDir).Path

# Step 1: Enable IIS features
Enable-IISFeatures

# Step 2: Find or install PHP with CGI
Write-Info "Locating PHP CGI binary..."
$phpCgi = Resolve-PhpBin -Preferred $PhpBin

if (-not $phpCgi) {
  Write-WarnMsg "No PHP CGI binary found. Installing portable PHP..."
  $phpCgi = Install-PhpPortableForIIS
}

if (-not $phpCgi -or -not (Test-Path -LiteralPath $phpCgi)) {
  Die "Could not find or install php-cgi.exe. Install PHP manually and pass -PhpBin."
}

Write-Ok "PHP CGI binary: $phpCgi"

# Step 3: Enable PHP extensions and IIS-specific settings
$phpDir = Split-Path -Parent $phpCgi
$phpIni = Join-Path $phpDir 'php.ini'
if (-not (Test-Path -LiteralPath $phpIni)) {
  $iniDev = Join-Path $phpDir 'php.ini-development'
  if (Test-Path -LiteralPath $iniDev) {
    Copy-Item -LiteralPath $iniDev -Destination $phpIni
  } else {
    Die "No php.ini found at $phpIni and no php.ini-development template."
  }
}
Enable-PhpExtensions -PhpIni $phpIni

# Step 4: Register PHP as FastCGI handler in IIS
Register-PhpFastCgi -PhpCgiPath $phpCgi

# Step 5: Optionally install URL Rewrite module
$hasRewrite = Install-UrlRewriteModule

# Step 6: Create IIS site + web.config
Set-SpotwebIISite -SpotwebDir $SpotwebDir -PhpCgiPath $phpCgi -SiteName $SiteName -Port $Port

# Step 7: Add hosts file entry
$hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
try {
  $hostsRaw = Get-Content -LiteralPath $hostsPath -Raw -ErrorAction Stop
  if ($hostsRaw -notmatch "(?im)^\s*127\.0\.0\.1\s+$([regex]::Escape($SiteName))\b") {
    Add-Content -LiteralPath $hostsPath -Value "`r`n127.0.0.1`t$SiteName`r`n" -Encoding ASCII
    Write-Ok "Added hosts entry: 127.0.0.1 $SiteName"
  } else {
    Write-Ok "Hosts entry already exists for $SiteName"
  }
} catch {
  Write-WarnMsg "Could not update hosts file: $($_.Exception.Message)"
  Write-WarnMsg "Add manually: 127.0.0.1 $SiteName"
}

# Step 8: Start the site
if (-not $SkipIISRestart) {
  Write-Info "Starting IIS site '$SiteName'..."
  Invoke-AppCmd @('start', 'site', $SiteName)

  # Ensure W3SVC service is running
  $w3svc = Get-Service -Name 'W3SVC' -ErrorAction SilentlyContinue
  if ($w3svc -and $w3svc.Status -ne 'Running') {
    Write-Info "Starting W3SVC service..."
    Start-Service -Name 'W3SVC'
  }
  Write-Ok "IIS site started"
}

# Step 9: Test PHP via IIS
Write-Info "Testing PHP through IIS..."
$testUrl = "http://127.0.0.1:$Port/"
if ($Port -eq 80) { $testUrl = "http://127.0.0.1/" }
try {
  $resp = Invoke-WebRequest -Uri $testUrl -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop
  if ($resp.StatusCode -eq 200) {
    Write-Ok "IIS is serving Spotweb at $testUrl (HTTP $($resp.StatusCode))"
  } else {
    Write-WarnMsg "IIS responded with HTTP $($resp.StatusCode) - check Spotweb configuration"
  }
} catch {
  Write-WarnMsg "Could not connect to IIS at $testUrl : $($_.Exception.Message)"
  Write-WarnMsg "The site may need a few seconds to start. Try opening $testUrl in your browser."
}

# Summary
Write-Host ""
Write-Ok "IIS + PHP FastCGI is configured for Spotweb"
Write-Host ""
Write-Host "Performance advantages over php -S:"
Write-Host "  - Multi-process FastCGI worker pool"
Write-Host "  - Static file caching via IIS output cache"
Write-Host "  - Kernel-mode caching for anonymous requests"
Write-Host "  - Proper process recycling and health checks"
Write-Host ""
Write-Host "1) Stop any php -S server on port 9999 (Ctrl+C in that window)"
Write-Host "2) Open:"
if ($Port -eq 80) {
  Write-Host "   http://$SiteName/"
  Write-Host "   http://127.0.0.1/"
} else {
  Write-Host "   http://${SiteName}:$Port/"
  Write-Host "   http://127.0.0.1:$Port/"
}
Write-Host ""
Write-Host "Login: admin / spotweb (unless you changed it)"
Write-Host ""
Write-Host "Manage IIS:"
Write-Host "  - IIS Manager: inetmgr"
Write-Host "  - Restart site: appcmd start site $SiteName"
Write-Host "  - Stop site:    appcmd stop site $SiteName"
Write-Host "  - Check status: appcmd list site $SiteName"
Write-Host ""
if (-not $hasRewrite) {
  Write-WarnMsg "URL Rewrite module was not installed. Spotweb uses query-string URLs"
  Write-WarnMsg "and works without it, but install it for future clean-URL support."
  Write-WarnMsg "Download: https://www.iis.net/downloads/microsoft/url-rewrite"
  Write-Host ""
}
Write-WarnMsg "If port $Port is busy (another IIS site, Skype, etc.):"
Write-WarnMsg "  re-run with: -Port 8080"
Write-Host ""
Write-WarnMsg "To revert to php -S dev server, simply stop the IIS site:"
Write-WarnMsg "  appcmd stop site $SiteName"
Write-Host ""
