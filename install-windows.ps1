# Spotweb Windows Installer v2.2.9 (Windows PowerShell 5.1 compatible)
<#
.SYNOPSIS
  Install Spotweb + VenimK theme pack on Windows (PowerShell).

.DESCRIPTION
  - Installs Git / PHP / MariaDB via winget when missing (optional)
  - Clones Spotweb (master or develop)
  - Creates MySQL/MariaDB database + dbsettings.inc.php
  - Installs update-safe /custom/ themes
  - Applies VenimK Spotweb overlays (NZB panel fix, router, modern UX)
  - Initializes DB schema and resets admin password to "spotweb"

.EXAMPLE
  Set-ExecutionPolicy -Scope Process Bypass
  .\Install-Spotweb.ps1

.EXAMPLE
  # Cache-bust download (recommended on Windows):
  Invoke-WebRequest -Headers @{ 'Cache-Control' = 'no-cache' } -Uri "https://raw.githubusercontent.com/VenimK/spotweb/themes-only/Install-Spotweb.ps1?$(Get-Random)" -OutFile Install-Spotweb.ps1
#>
[CmdletBinding()]
param(
  [string]$SpotwebDir = (Join-Path $env:USERPROFILE 'Spotweb'),
  [string]$DbName = 'spotweb',
  [string]$DbUser = 'spotweb',
  [string]$DbPass = 'spotweb',
  [ValidateSet('none', 'dark', 'pack')]
  [string]$ThemeMode = 'pack',
  [ValidateSet('master', 'develop')]
  [string]$SpotwebBranch = 'master',
  [int]$Port = 9999,
  [switch]$SkipPackageInstall,
  [switch]$NonInteractive
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$GithubRawBase = 'https://raw.githubusercontent.com/VenimK/spotweb/themes-only'
$SpotwebGitRepo = 'https://github.com/spotweb/spotweb.git'
$StartTime = Get-Date
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

function Write-Info([string]$Message) { Write-Host "i  $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "OK $Message" -ForegroundColor Green }
function Write-WarnMsg([string]$Message) { Write-Host "!  $Message" -ForegroundColor Yellow }
function Die([string]$Message) { Write-Host "X  $Message" -ForegroundColor Red; exit 1 }

function Test-IsAdmin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $principal = New-Object Security.Principal.WindowsPrincipal($id)
  return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Ensure-Command([string]$Name) {
  if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
    Die "Missing required command: $Name"
  }
}

function Read-Default([string]$Prompt, [string]$Default) {
  if ($NonInteractive) { return $Default }
  $v = Read-Host "$Prompt [$Default]"
  if ([string]::IsNullOrWhiteSpace($v)) { return $Default }
  return $v
}

function Install-WingetPackage([string]$Id, [string]$DisplayName) {
  if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
    Write-WarnMsg "winget not found; skip installing $DisplayName"
    return $false
  }
  Write-Info "Installing $DisplayName via winget ($Id)..."
  # Prefer per-user scope when possible (avoids some admin/UAC failures)
  $argSets = @(
    @('install', '--id', $Id, '-e', '--accept-package-agreements', '--accept-source-agreements', '--scope', 'user'),
    @('install', '--id', $Id, '-e', '--accept-package-agreements', '--accept-source-agreements'),
    @('install', '--name', $Id, '--accept-package-agreements', '--accept-source-agreements')
  )
  foreach ($args in $argSets) {
    & winget @args
    if ($LASTEXITCODE -eq 0) {
      Write-Ok "$DisplayName installed"
      Refresh-Path
      return $true
    }
  }
  Write-WarnMsg "winget install failed for $DisplayName (last exit $LASTEXITCODE)"
  return $false
}

function Refresh-Path {
  $machine = [Environment]::GetEnvironmentVariable('Path', 'Machine')
  $user = [Environment]::GetEnvironmentVariable('Path', 'User')
  $env:Path = @($machine, $user, $env:Path) -join ';'
}

function Add-ToUserPath([string]$Dir) {
  if (-not (Test-Path -LiteralPath $Dir)) { return }
  $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
  $parts = @()
  if ($userPath) { $parts = $userPath.Split(';') | Where-Object { $_ -and $_.Trim() -ne '' } }
  if ($parts -contains $Dir) {
    Refresh-Path
    return
  }
  $newPath = if ($userPath) { "$userPath;$Dir" } else { $Dir }
  [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
  $env:Path = "$env:Path;$Dir"
  Write-Ok "Added to user PATH: $Dir"
}

function Resolve-Php {
  Refresh-Path
  # Prefer our portable build over any older WinGet PHP earlier on PATH
  $portable = Join-Path $env:LOCALAPPDATA 'SpotwebTools\php\php.exe'
  if (Test-Path -LiteralPath $portable) {
    $env:Path = "$(Split-Path -Parent $portable);" + $env:Path
    return $portable
  }

  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd -and ($cmd.Source -notlike '*PHP.PHP.8.1*')) { return $cmd.Source }

  $searchRoots = @(
    (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'),
    (Join-Path $env:LOCALAPPDATA 'Programs'),
    'C:\php',
    'C:\tools\php',
    "$env:ProgramFiles\PHP",
    "${env:ProgramFiles(x86)}\PHP",
    $env:ProgramFiles
  ) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }

  foreach ($root in $searchRoots) {
    try {
      $hits = @(Get-ChildItem -Path $root -Filter php.exe -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -notmatch '\\tests\\|\\test\\|PHP\.PHP\.8\.1' })
      # Prefer higher version folder names when possible
      $hit = $hits | Sort-Object FullName -Descending | Select-Object -First 1
      if ($hit) { return $hit.FullName }
    } catch { }
  }
  return $null
}

function Get-PhpVersion([string]$PhpBin) {
  if (-not $PhpBin -or -not (Test-Path -LiteralPath $PhpBin)) { return $null }
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    $ver = (& $PhpBin -r "echo PHP_VERSION;" 2>$null)
  } finally {
    $ErrorActionPreference = $oldEap
  }
  if (-not $ver) { return $null }
  return "$ver".Trim()
}

function Test-PhpUsable([string]$PhpBin) {
  if (-not $PhpBin -or -not (Test-Path -LiteralPath $PhpBin)) { return $false }
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    $verRaw = (& $PhpBin -r "echo PHP_VERSION;" 2>$null)
    $out = & $PhpBin -m 2>&1 | Out-String
    $code = $LASTEXITCODE
  } finally {
    $ErrorActionPreference = $oldEap
  }
  if ($code -ne 0) { return $false }
  # Spotweb develop/composer currently requires PHP >= 8.2
  try {
    $ver = [version](("$verRaw").Trim() -replace '-.*$','')
    if ($ver -lt [version]'8.2.0') { return $false }
  } catch {
    return $false
  }
  foreach ($mod in @('pdo_mysql', 'mysqli', 'curl', 'mbstring', 'openssl')) {
    if ($out -notmatch "(?im)^\s*$mod\s*$") { return $false }
  }
  return $true
}

function Install-PhpPortable {
  param([switch]$Force)
  <#
    Download full NTS x64 ZIP from windows.php.net into %LOCALAPPDATA%\SpotwebTools\php.
    Prefer this over incomplete WinGet PHP packages on Windows.
  #>
  $targetRoot = Join-Path $env:LOCALAPPDATA 'SpotwebTools\php'
  $phpExe = Join-Path $targetRoot 'php.exe'
  if ((-not $Force) -and (Test-Path -LiteralPath $phpExe) -and (Test-PhpUsable $phpExe)) {
    Add-ToUserPath $targetRoot
    # Put portable PHP first on this session PATH
    $env:Path = "$targetRoot;" + $env:Path
    Write-Ok "Using existing portable PHP: $phpExe"
    return $phpExe
  }

  Write-Info "Downloading portable PHP (NTS x64) from windows.php.net..."
  $releasesUrl = 'https://windows.php.net/downloads/releases/'
  try {
    $html = (Invoke-WebRequest -Uri $releasesUrl -UseBasicParsing).Content
  } catch {
    Write-WarnMsg "Could not list PHP releases: $($_.Exception.Message)"
    return $null
  }

  # Prefer newest 8.3/8.4 NTS VS16/VS17 x64 zip
  $pattern = 'href="(php-(8\.[3-4]\.\d+)-nts-Win32-vs1[67]-x64\.zip)"'
  $matches = [regex]::Matches($html, $pattern, 'IgnoreCase')
  if ($matches.Count -eq 0) {
    # archives fallback
    $releasesUrl = 'https://windows.php.net/downloads/releases/archives/'
    try { $html = (Invoke-WebRequest -Uri $releasesUrl -UseBasicParsing).Content } catch { }
    $matches = [regex]::Matches($html, $pattern, 'IgnoreCase')
  }
  if ($matches.Count -eq 0) {
    Write-WarnMsg "No suitable PHP NTS x64 zip found on windows.php.net"
    return $null
  }
  $file = $matches[0].Groups[1].Value
  $url = $releasesUrl.TrimEnd('/') + '/' + $file
  Write-Info "Fetching $file"

  $tmp = Join-Path ([System.IO.Path]::GetTempPath()) $file
  try {
    Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing
  } catch {
    Write-WarnMsg "PHP download failed: $($_.Exception.Message)"
    return $null
  }

  if (Test-Path -LiteralPath $targetRoot) {
    Remove-Item -LiteralPath $targetRoot -Recurse -Force -ErrorAction SilentlyContinue
  }
  New-Item -ItemType Directory -Path $targetRoot -Force | Out-Null
  Expand-Archive -LiteralPath $tmp -DestinationPath $targetRoot -Force
  Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue

  if (-not (Test-Path -LiteralPath $phpExe)) {
    Write-WarnMsg "php.exe missing after extract to $targetRoot"
    return $null
  }

  $ini = Join-Path $targetRoot 'php.ini'
  $iniDev = Join-Path $targetRoot 'php.ini-development'
  if (Test-Path -LiteralPath $iniDev) {
    Copy-Item -LiteralPath $iniDev -Destination $ini -Force
  }

  Add-ToUserPath $targetRoot
  $env:Path = "$targetRoot;" + $env:Path
  Write-Ok "Portable PHP installed: $phpExe"

  # php.ini-development has extensions commented out — enable before usability checks
  Enable-PhpExtensions -PhpBin $phpExe
  return $phpExe
}

function Ensure-PhpInstalled {
  # Prefer Spotweb portable PHP first (avoid stale WinGet 8.1 earlier on PATH)
  $portablePath = Join-Path $env:LOCALAPPDATA 'SpotwebTools\php\php.exe'
  if (Test-Path -LiteralPath $portablePath) {
    $env:Path = "$(Split-Path -Parent $portablePath);" + $env:Path
    Enable-PhpExtensions -PhpBin $portablePath
    if (Test-PhpUsable $portablePath) {
      Write-Ok "Using portable PHP: $portablePath ($(Get-PhpVersion $portablePath))"
      return $portablePath
    }
  }

  $existing = Resolve-Php
  if ($existing -and (Test-PhpUsable $existing)) {
    return $existing
  }
  if ($existing) {
    Write-WarnMsg "Existing PHP is incomplete/unusable: $existing ($(Get-PhpVersion $existing))"
    Write-WarnMsg "Installing a full portable PHP build instead..."
  }

  [void](Install-WingetPackage -Id 'Microsoft.VCRedist.2015+.x64' -DisplayName 'Visual C++ Redistributable')

  $portable = Install-PhpPortable -Force
  if ($portable) {
    Enable-PhpExtensions -PhpBin $portable
    if (Test-PhpUsable $portable) { return $portable }
    Write-WarnMsg "Portable PHP at $portable still failed module/version checks ($(Get-PhpVersion $portable))"
  } else {
    Write-WarnMsg "Portable PHP download/extract failed; trying winget PHP packages..."
  }

  $ids = @(
    'PHP.PHP.NTS.8.3', 'PHP.PHP.8.3',
    'PHP.PHP.NTS.8.4', 'PHP.PHP.8.4',
    'PHP.PHP.NTS.8.2', 'PHP.PHP.8.2'
  )
  foreach ($id in $ids) {
    if (Install-WingetPackage -Id $id -DisplayName "PHP ($id)") {
      # Prefer newly installed package paths over old 8.1
      Refresh-Path
      $env:Path = "$(Join-Path $env:LOCALAPPDATA 'SpotwebTools\php');" + $env:Path
      $php = Resolve-Php
      if ($php -and $php -like '*PHP.PHP.8.1*') {
        # Explicitly ignore known-bad WinGet 8.1 package
        $php = $null
      }
      if ($php) {
        Enable-PhpExtensions -PhpBin $php
        if (Test-PhpUsable $php) { return $php }
      }
    }
  }

  return $null
}

function Resolve-Mysql {
  Refresh-Path
  foreach ($name in @('mysql', 'mariadb')) {
    $cmd = Get-Command $name -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
  }
  $candidates = @(
    'C:\Program Files\MariaDB*\bin\mysql.exe',
    'C:\Program Files\MySQL*\bin\mysql.exe',
    'C:\xampp\mysql\bin\mysql.exe',
    'C:\laragon\bin\mysql\*\bin\mysql.exe'
  )
  foreach ($pattern in $candidates) {
    $hit = Get-Item $pattern -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($hit) { return $hit.FullName }
  }
  return $null
}

function Enable-PhpExtensions([string]$PhpBin) {
  $phpDir = Split-Path -Parent $PhpBin
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    $iniOut = & $PhpBin --ini 2>&1 | Out-String
  } finally {
    $ErrorActionPreference = $oldEap
  }

  $phpIni = $null
  $m = [regex]::Match($iniOut, 'Loaded Configuration File:\s*(.+)')
  if ($m.Success) {
    $loaded = $m.Groups[1].Value.Trim()
    if ($loaded -and $loaded -ne '(none)' -and (Test-Path -LiteralPath $loaded)) {
      $phpIni = $loaded
    }
  }

  $candidate = Join-Path $phpDir 'php.ini'
  $dev = Join-Path $phpDir 'php.ini-development'
  $prod = Join-Path $phpDir 'php.ini-production'
  if (-not $phpIni) {
    if (-not (Test-Path -LiteralPath $candidate)) {
      if (Test-Path -LiteralPath $dev) {
        Copy-Item -LiteralPath $dev -Destination $candidate -Force
        Write-Ok "Created php.ini from php.ini-development"
      } elseif (Test-Path -LiteralPath $prod) {
        Copy-Item -LiteralPath $prod -Destination $candidate -Force
        Write-Ok "Created php.ini from php.ini-production"
      }
    }
    if (Test-Path -LiteralPath $candidate) { $phpIni = $candidate }
  }

  if (-not $phpIni) {
    Write-WarnMsg "Could not locate/create php.ini next to $PhpBin"
    return
  }

  Write-Info "Ensuring common PHP extensions in $phpIni"
  $raw = Get-Content -LiteralPath $phpIni -Raw
  $extDir = Join-Path $phpDir 'ext'
  if (Test-Path -LiteralPath $extDir) {
    if ($raw -match '(?im)^\s*;?\s*extension_dir\s*=') {
      $raw = [regex]::Replace($raw, '(?im)^\s*;?\s*extension_dir\s*=.*$', "extension_dir=`"$extDir`"")
    } else {
      $raw += "`r`nextension_dir=`"$extDir`""
    }
  }

  # Only enable extensions whose DLLs actually exist (avoids WinGet incomplete packages)
  $wanted = @('curl', 'fileinfo', 'gd', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'xml', 'zip')
  foreach ($ext in $wanted) {
    $dll = Join-Path $extDir "php_$ext.dll"
    if (-not (Test-Path -LiteralPath $dll)) {
      Write-WarnMsg "Skipping extension=$ext (missing php_$ext.dll)"
      continue
    }
    # Uncomment existing lines
    $raw = [regex]::Replace($raw, "(?im)^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$", "extension=$ext")
    # Avoid duplicates: if already enabled once, strip extras later
    if ($raw -notmatch "(?im)^\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$") {
      $raw += "`r`nextension=$ext"
    }
  }

  # Remove stale extension= lines for missing DLLs (left behind by older installer runs)
  $lines = $raw -split "`r?`n"
  $cleaned = New-Object System.Collections.Generic.List[string]
  foreach ($line in $lines) {
    $em = [regex]::Match($line, '(?im)^\s*extension\s*=\s*(php_)?([A-Za-z0-9_]+)(\.dll)?\s*$')
    if ($em.Success) {
      $extName = $em.Groups[2].Value
      $dllPath = Join-Path $extDir ("php_" + $extName + ".dll")
      if ($extDir -and -not (Test-Path -LiteralPath $dllPath)) { continue }
    }
    $cleaned.Add($line)
  }
  $raw = ($cleaned -join "`r`n")

  # Deduplicate extension= lines
  $lines = $raw -split "`r?`n"
  $seen = @{}
  $out = New-Object System.Collections.Generic.List[string]
  foreach ($line in $lines) {
    $em = [regex]::Match($line, '(?im)^\s*extension\s*=\s*(php_)?([A-Za-z0-9_]+)(\.dll)?\s*$')
    if ($em.Success) {
      $key = $em.Groups[2].Value.ToLowerInvariant()
      if ($seen.ContainsKey($key)) { continue }
      $seen[$key] = $true
    }
    $out.Add($line)
  }
  $raw = ($out -join "`r`n")
  Set-Content -LiteralPath $phpIni -Value $raw -Encoding UTF8
  Write-Ok "Updated php.ini extensions (only DLLs present in ext\)"
}

function Get-MariaDbInstallDir {
  $hits = @()
  foreach ($pattern in @(
      'C:\Program Files\MariaDB*',
      'C:\Program Files\MySQL\MySQL Server*'
    )) {
    $hits += Get-Item $pattern -ErrorAction SilentlyContinue
  }
  if ($hits.Count -eq 0) { return $null }
  return ($hits | Sort-Object FullName -Descending | Select-Object -First 1).FullName
}

function Ensure-MariaDbWindowsService {
  <#
    Winget often installs MariaDB files without a running service.
    Try to find/create/start the Windows service.
  #>
  Write-Info "Looking for MariaDB/MySQL Windows service..."

  $svcs = @(Get-Service -ErrorAction SilentlyContinue | Where-Object {
      $_.Name -match '(?i)maria|mysql' -or $_.DisplayName -match '(?i)MariaDB|MySQL'
    })

  if ($svcs.Count -eq 0) {
    Write-WarnMsg "No MariaDB/MySQL service registered yet. Trying to create one..."
    $installDir = Get-MariaDbInstallDir
    if (-not $installDir) {
      Write-WarnMsg "MariaDB install directory not found under Program Files."
      return $false
    }
    $mysqld = Join-Path $installDir 'bin\mysqld.exe'
    $mysqlInstallDb = Join-Path $installDir 'bin\mysql_install_db.exe'
    if (-not (Test-Path -LiteralPath $mysqld)) {
      Write-WarnMsg "mysqld.exe not found in $installDir"
      return $false
    }

    # Some packages ship mysql_install_db for first-time data dir init
    $dataDir = Join-Path $installDir 'data'
    if ((Test-Path -LiteralPath $mysqlInstallDb) -and -not (Test-Path -LiteralPath $dataDir)) {
      Write-Info "Initializing MariaDB data directory..."
      try {
        $oldEap = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        & $mysqlInstallDb 2>&1 | Out-Host
        $ErrorActionPreference = $oldEap
      } catch {
        Write-WarnMsg "mysql_install_db failed: $($_.Exception.Message)"
      }
    }

    Write-Info "Registering Windows service via: $mysqld --install"
    try {
      $oldEap = $ErrorActionPreference
      $ErrorActionPreference = 'Continue'
      & $mysqld --install 2>&1 | Out-Host
      $ErrorActionPreference = $oldEap
    } catch {
      Write-WarnMsg "mysqld --install failed: $($_.Exception.Message)"
      Write-WarnMsg "Re-run this installer from an elevated PowerShell (Run as administrator)."
      return $false
    }

    Start-Sleep -Seconds 2
    $svcs = @(Get-Service -ErrorAction SilentlyContinue | Where-Object {
        $_.Name -match '(?i)maria|mysql' -or $_.DisplayName -match '(?i)MariaDB|MySQL'
      })
  }

  if ($svcs.Count -eq 0) {
    # Last-ditch known names
    foreach ($name in @('MariaDB', 'MySQL', 'MySQL80', 'MySQL57', 'mariadb')) {
      $svc = Get-Service -Name $name -ErrorAction SilentlyContinue
      if ($svc) { $svcs += $svc }
    }
  }

  if ($svcs.Count -eq 0) {
    Write-WarnMsg "Still no MariaDB/MySQL Windows service."
    Write-Host ""
    Write-Host "Fix manually (elevated PowerShell):" -ForegroundColor Yellow
    Write-Host '  cd "C:\Program Files\MariaDB 12.3\bin"'
    Write-Host "  .\mysqld --install"
    Write-Host "  Start-Service MySQL"
    Write-Host "  # or: Start-Service MariaDB"
    Write-Host ""
    return $false
  }

  $started = $false
  foreach ($svc in $svcs) {
    try {
      if ($svc.Status -ne 'Running') {
        Write-Info "Starting service: $($svc.Name) ($($svc.DisplayName))"
        Start-Service -Name $svc.Name -ErrorAction Stop
      }
      $svc.Refresh()
      if ($svc.Status -eq 'Running') {
        Write-Ok "Service running: $($svc.Name)"
        $started = $true
      }
    } catch {
      Write-WarnMsg "Could not start $($svc.Name): $($_.Exception.Message)"
      Write-WarnMsg "Open an elevated PowerShell and run: Start-Service $($svc.Name)"
    }
  }
  return $started
}

function Wait-MysqlReady {
  param(
    [string]$MysqlBin,
    [string]$Password = '',
    [int]$Tries = 30,
    [int]$DelaySeconds = 2
  )
  Write-Info "Waiting for MariaDB/MySQL to accept connections..."
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    for ($i = 1; $i -le $Tries; $i++) {
      $args = @('-h127.0.0.1', '-uroot')
      if (-not [string]::IsNullOrEmpty($Password)) { $args += "-p$Password" }
      $args += @('-e', 'SELECT 1;')
      $null = & $MysqlBin @args 2>&1
      if ($LASTEXITCODE -eq 0) {
        Write-Ok "MariaDB/MySQL is ready"
        return $true
      }
      Write-Info "  attempt $i/$Tries - not ready yet..."
      Start-Sleep -Seconds $DelaySeconds
    }
  } finally {
    $ErrorActionPreference = $oldEap
  }
  return $false
}

function Invoke-MysqlSql {
  param(
    [string]$MysqlBin,
    [string]$Sql,
    [string]$User = 'root',
    [string]$Password = '',
    [string]$Database = '',
    [string]$HostAddress = '127.0.0.1'
  )
  # Prefer TCP 127.0.0.1 on Windows (avoids some named-pipe localhost quirks)
  $args = @("-h$HostAddress", "-u$user")
  if (-not [string]::IsNullOrEmpty($Password)) { $args += "-p$Password" }
  if ($Database) { $args += $Database }
  $args += @('-e', $Sql)
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    $output = & $MysqlBin @args 2>&1
    $code = $LASTEXITCODE
  } finally {
    $ErrorActionPreference = $oldEap
  }
  if ($code -ne 0) {
    throw "mysql failed ($code): $Sql`n$($output | Out-String)"
  }
}

function Download-File([string]$Url, [string]$OutFile) {
  $dir = Split-Path -Parent $OutFile
  if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  Invoke-WebRequest -Uri $Url -OutFile $OutFile -UseBasicParsing
}

function Install-Spotweb([string]$Dir, [string]$Branch) {
  Write-Info "Downloading Spotweb ($Branch)..."
  if (Test-Path -LiteralPath $Dir) {
    Write-WarnMsg "Removing existing directory: $Dir"
    Remove-Item -LiteralPath $Dir -Recurse -Force
  }
  $parent = Split-Path -Parent $Dir
  if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
  Ensure-Command git
  & git clone -b $Branch --depth 1 $SpotwebGitRepo $Dir
  if ($LASTEXITCODE -ne 0) { Die "Failed to clone Spotweb" }
  New-Item -ItemType Directory -Path (Join-Path $Dir 'cache') -Force | Out-Null
  Write-Ok "Spotweb downloaded"
}

function Write-DbSettings([string]$Dir, [string]$Name, [string]$User, [string]$Pass) {
  Write-Info "Creating dbsettings.inc.php / ownsettings.php"
  @"
<?php
`$dbsettings['engine'] = 'mysql';
`$dbsettings['host'] = '127.0.0.1';
`$dbsettings['dbname'] = '$Name';
`$dbsettings['user'] = '$User';
`$dbsettings['pass'] = '$Pass';
?>
"@ | Set-Content -LiteralPath (Join-Path $Dir 'dbsettings.inc.php') -Encoding UTF8

  @"
<?php
error_reporting(E_ALL);
`$settings['custom_stylesheet'] = '';
?>
"@ | Set-Content -LiteralPath (Join-Path $Dir 'ownsettings.php') -Encoding UTF8
}

function Patch-TemplateHeaders([string]$Dir) {
  # Single-quoted here-string: safest on Windows PowerShell 5.1
  $hook = @'
<?php
if (file_exists(__DIR__ . '/../../../custom/includes/theme-loader.inc.php')) {
    include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php');
}
?>
'@
  Get-ChildItem -Path (Join-Path $Dir 'templates') -Recurse -Filter 'header.inc.php' -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -match '\\includes\\header\.inc\.php$' } |
    ForEach-Object {
      $txt = Get-Content -LiteralPath $_.FullName -Raw
      if ($txt -match 'custom/includes/theme-loader\.inc\.php') { return }
      if ($txt -match '</head>') {
        $txt = $txt -replace '</head>', ($hook + '</head>')
        Set-Content -LiteralPath $_.FullName -Value $txt -Encoding UTF8
      }
    }
}

function Install-Themes([string]$Dir, [string]$Mode) {
  if ($Mode -eq 'none') {
    Write-Info "Skipping theme installation"
    return
  }

  Write-Info "Creating /custom/ theme structure (update-safe)"
  @(
    'custom\themes\preinstalled',
    'custom\js',
    'custom\tools',
    'custom\includes'
  ) | ForEach-Object { New-Item -ItemType Directory -Path (Join-Path $Dir $_) -Force | Out-Null }

  if ($Mode -eq 'pack') {
    $themes = @('dark','midnight-ocean','cyberpunk','nord','dracula','forest','sunset','spring','summer','autumn','winter')
    foreach ($theme in $themes) {
      Write-Info "Downloading theme-$theme.css"
      try {
        Download-File "$GithubRawBase/custom/themes/preinstalled/theme-$theme.css" (Join-Path $Dir "custom\themes\preinstalled\theme-$theme.css")
      } catch { Write-WarnMsg "Failed to download theme-$theme.css" }
    }
    foreach ($pair in @(
      @('custom/js/theme-switcher.js', 'custom\js\theme-switcher.js'),
      @('custom/tools/theme-customizer.html', 'custom\tools\theme-customizer.html'),
      @('custom/tools/theme-upload.php', 'custom\tools\theme-upload.php'),
      @('custom/tools/.htaccess', 'custom\tools\.htaccess'),
      @('custom/includes/theme-loader.inc.php', 'custom\includes\theme-loader.inc.php'),
      @('custom/README.md', 'custom\README.md'),
      @('custom/update-themes.sh', 'custom\update-themes.sh')
    )) {
      try { Download-File "$GithubRawBase/$($pair[0])" (Join-Path $Dir $pair[1]) } catch { Write-WarnMsg "Failed: $($pair[0])" }
    }
    Patch-TemplateHeaders -Dir $Dir
    Write-Ok "Theme pack installed"
    return
  }

  if ($Mode -eq 'dark') {
    Download-File "$GithubRawBase/custom/themes/preinstalled/theme-dark.css" (Join-Path $Dir 'custom\themes\preinstalled\theme-dark.css')
    Patch-TemplateHeaders -Dir $Dir
    Write-Ok "Dark theme installed"
  }
}

function Apply-Overlays([string]$Dir) {
  $localApply = Join-Path $ScriptDir 'apply-spotweb-overlays.ps1'
  $applyPath = $localApply
  if (-not (Test-Path -LiteralPath $applyPath)) {
    Write-Info "Downloading apply-spotweb-overlays.ps1..."
    $applyPath = Join-Path ([System.IO.Path]::GetTempPath()) ('apply-spotweb-overlays-' + [guid]::NewGuid().ToString('N') + '.ps1')
    try {
      Download-File "$GithubRawBase/apply-spotweb-overlays.ps1" $applyPath
    } catch {
      Write-WarnMsg "Could not download apply-spotweb-overlays.ps1; skipping overlays"
      return
    }
  }
  Write-Info "Applying Spotweb overlays..."
  & powershell -NoProfile -ExecutionPolicy Bypass -File $applyPath -SpotwebDir $Dir
}

function Ensure-MasterTemplateCompat([string]$MysqlBin, [string]$Name, [string]$User, [string]$Pass) {
  try {
    # Single-quoted here-string avoids all PS 5.1 quote-escaping pitfalls
    $sql = @'
UPDATE usersettings SET otherprefs = REPLACE(otherprefs, 's:6:"modern"', 's:6:"we1rdo"');
'@
    Invoke-MysqlSql -MysqlBin $MysqlBin -User $User -Password $Pass -Database $Name -Sql $sql.Trim()
  } catch {
    Write-WarnMsg "Template compat update skipped"
  }
}

# -------------------- main --------------------
Write-Host ""
Write-Host "Spotweb Windows Installer (PowerShell)" -ForegroundColor Green
Write-Host "======================================"
Write-Host ""

if (-not $NonInteractive) {
  $SpotwebDir = Read-Default "Install directory" $SpotwebDir
  $DbName = Read-Default "Database name" $DbName
  $DbUser = Read-Default "Database user" $DbUser
  $DbPass = Read-Default "Database password" $DbPass

  Write-Host ""
  Write-Host "Theme Options:"
  Write-Host "  1) No themes (Light only)"
  Write-Host "  2) Dark mode only"
  Write-Host "  3) Complete theme pack (11 themes + switcher + tools)"
  $themeChoice = Read-Default "Select theme option" '3'
  switch ($themeChoice) {
    '1' { $ThemeMode = 'none' }
    '2' { $ThemeMode = 'dark' }
    default { $ThemeMode = 'pack' }
  }

  $Port = [int](Read-Default "Local web port for PHP built-in server" "$Port")

  Write-Host ""
  Write-Host "Spotweb Version:"
  Write-Host "  1) master (stable)"
  Write-Host "  2) develop (development)"
  $refChoice = Read-Default "Select Spotweb version" '1'
  switch ($refChoice) {
    '2' { $SpotwebBranch = 'develop' }
    default { $SpotwebBranch = 'master' }
  }
}

if (-not $SkipPackageInstall) {
  Write-Info "Checking / installing dependencies (winget)..."
  if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    if (-not (Install-WingetPackage -Id 'Git.Git' -DisplayName 'Git')) {
      Die "Git is required. Install Git for Windows and re-run."
    }
    Refresh-Path
  }
  $currentPhp = Resolve-Php
  if (-not $currentPhp -or -not (Test-PhpUsable $currentPhp)) {
    $phpInstalled = Ensure-PhpInstalled
    if (-not $phpInstalled -or -not (Test-PhpUsable $phpInstalled)) {
      Write-WarnMsg "Automatic PHP install failed or incomplete."
      Write-Host ""
      Write-Host "Manual fix (recommended):" -ForegroundColor Yellow
      Write-Host "  Download NTS x64 ZIP from https://windows.php.net/download/"
      Write-Host "  Extract to $env:LOCALAPPDATA\SpotwebTools\php"
      Write-Host "  Copy php.ini-development to php.ini, enable curl/mbstring/mysqli/openssl/pdo_mysql"
      Write-Host ""
      Write-Host "Then re-run:"
      Write-Host "  .\Install-Spotweb.ps1 -SkipPackageInstall"
      Die "PHP not usable"
    }
  }
  if (-not (Resolve-Mysql)) {
    $ok = $false
    foreach ($id in @('MariaDB.Server', 'Oracle.MySQL', 'MariaDB.MariaDB')) {
      if (Install-WingetPackage -Id $id -DisplayName "MariaDB/MySQL ($id)") { $ok = $true; break }
    }
    if (-not $ok) {
      Write-WarnMsg "Could not install MariaDB/MySQL automatically. Install a local server (or XAMPP/Laragon) and ensure mysql.exe is on PATH."
    }
    Refresh-Path
  }
}

$PhpBin = Resolve-Php
if (-not $PhpBin -or -not (Test-PhpUsable $PhpBin)) {
  Write-Info "Installing/repairing PHP via portable build..."
  $PhpBin = Ensure-PhpInstalled
}
if (-not $PhpBin -or -not (Test-PhpUsable $PhpBin)) {
  Die "PHP not usable. Install portable PHP 8.3 NTS x64 from windows.php.net and re-run."
}
Write-Ok "Using PHP: $PhpBin"
Enable-PhpExtensions -PhpBin $PhpBin
# Re-test after php.ini updates
if (-not (Test-PhpUsable $PhpBin)) {
  Write-WarnMsg "PHP still reports missing modules after php.ini update; installing portable PHP..."
  $PhpBin = Install-PhpPortable -Force
  if ($PhpBin) { Enable-PhpExtensions -PhpBin $PhpBin }
}
if (-not (Test-PhpUsable $PhpBin)) {
  Die "PHP extensions incomplete. Portable PHP install failed."
}
Write-Ok ("PHP modules OK: " + ((& $PhpBin -r "echo implode(',', array_intersect(get_loaded_extensions(), ['pdo_mysql','mysqli','curl','mbstring','openssl']));") -join ''))

$MysqlBin = Resolve-Mysql
if (-not $MysqlBin) { Die "mysql client not found on PATH" }
Write-Ok "Using mysql: $MysqlBin"

# MariaDB must be running (ERROR 2002 / 10061 = service not listening)
[void](Ensure-MariaDbWindowsService)
$rootPass = ''
if (-not $NonInteractive) {
  $rootPass = Read-Host "MariaDB/MySQL root password (blank if none)"
}
if (-not (Wait-MysqlReady -MysqlBin $MysqlBin -Password $rootPass -Tries 30 -DelaySeconds 2)) {
  Write-Host ""
  Write-Host "MariaDB is installed but not accepting connections." -ForegroundColor Yellow
  Write-Host "Run these in an elevated PowerShell (Run as administrator):" -ForegroundColor Yellow
  Write-Host '  cd "C:\Program Files\MariaDB 12.3\bin"'
  Write-Host "  .\mysqld --install"
  Write-Host "  Get-Service *maria*,*mysql* | Format-Table Name,Status,DisplayName"
  Write-Host "  Start-Service MySQL"
  Write-Host "  # if service name is MariaDB: Start-Service MariaDB"
  Write-Host '  & ".\mysql.exe" -h127.0.0.1 -uroot -e "SELECT 1;"'
  Write-Host ""
  Write-Host "Then re-run:"
  Write-Host "  .\install-windows.ps1 -SkipPackageInstall"
  Die "Cannot connect to MariaDB/MySQL on 127.0.0.1 (is the service running?)"
}

Write-Info "Creating database and user..."
try {
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  # Create user for both localhost and 127.0.0.1 (Windows auth quirks)
  foreach ($hostName in @('localhost', '127.0.0.1')) {
    try { Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "CREATE USER IF NOT EXISTS '$DbUser'@'$hostName' IDENTIFIED BY '$DbPass';" } catch { }
    try { Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "ALTER USER '$DbUser'@'$hostName' IDENTIFIED BY '$DbPass';" } catch { }
    Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'$hostName';"
  }
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "FLUSH PRIVILEGES;"
  Write-Ok "Database configured"
} catch {
  Die "Database setup failed: $($_.Exception.Message). Check root password / that MariaDB is running."
}

Install-Spotweb -Dir $SpotwebDir -Branch $SpotwebBranch
Write-DbSettings -Dir $SpotwebDir -Name $DbName -User $DbUser -Pass $DbPass
Install-Themes -Dir $SpotwebDir -Mode $ThemeMode
Apply-Overlays -Dir $SpotwebDir

# Copy Windows starter next to Spotweb for convenience
$startSrc = Join-Path $ScriptDir 'Start-Spotweb.ps1'
if (-not (Test-Path -LiteralPath $startSrc)) {
  try {
    Download-File "$GithubRawBase/Start-Spotweb.ps1" (Join-Path $SpotwebDir 'Start-Spotweb.ps1')
  } catch { Write-WarnMsg "Could not download Start-Spotweb.ps1" }
} else {
  Copy-Item -LiteralPath $startSrc -Destination (Join-Path $SpotwebDir 'Start-Spotweb.ps1') -Force
}

Write-Info "Initializing Spotweb database schema..."
& $PhpBin (Join-Path $SpotwebDir 'bin\upgrade-db.php')
if ($LASTEXITCODE -ne 0) { Die "upgrade-db.php failed" }
Write-Ok "Database initialized"

Write-Info "Setting admin password (default: spotweb)..."
& $PhpBin (Join-Path $SpotwebDir 'bin\upgrade-db.php') '--reset-password' 'admin'
Write-Ok "Admin password set"

if ($SpotwebBranch -eq 'master') {
  Ensure-MasterTemplateCompat -MysqlBin $MysqlBin -Name $DbName -User $DbUser -Pass $DbPass
}

$elapsed = (Get-Date) - $StartTime
Write-Host ""
Write-Ok "Installation complete"
Write-Host ""
Write-Host "Access Spotweb (PHP built-in server):"
Write-Host "  1) Start server:"
Write-Host "     cd `"$SpotwebDir`""
Write-Host "     .\Start-Spotweb.ps1 -Port $Port"
Write-Host "     # or: `"$PhpBin`" -S 127.0.0.1:$Port -t `"$SpotwebDir`" `"$SpotwebDir\router.php`""
Write-Host "  2) Open:"
Write-Host "     http://127.0.0.1:$Port/"
Write-Host ""
Write-Host "Admin login:"
Write-Host "  Username: admin"
Write-Host "  Password: spotweb"
Write-Host ""
if ($ThemeMode -eq 'pack') {
  Write-Host "Theme tools:"
  Write-Host "  Customizer: http://127.0.0.1:$Port/custom/tools/theme-customizer.html"
  Write-Host "  Upload:     http://127.0.0.1:$Port/custom/tools/theme-upload.php"
  Write-Host ""
}
Write-Host "Health check:"
Write-Host "  `"$PhpBin`" `"$SpotwebDir\bin\doctor.php`""
Write-Host ""
Write-Host ("Completed in {0}m {1}s" -f [int]$elapsed.TotalMinutes, $elapsed.Seconds) -ForegroundColor Green
Write-Host ""
Write-WarnMsg "Tip: for production on Windows, prefer IIS + PHP FastCGI or Apache instead of php -S."
