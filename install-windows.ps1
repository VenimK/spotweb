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
  .\install-windows.ps1

.EXAMPLE
  irm https://raw.githubusercontent.com/VenimK/spotweb/themes-only/install-windows.ps1 | iex
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
  $args = @('install', '--id', $Id, '-e', '--accept-package-agreements', '--accept-source-agreements')
  & winget @args
  if ($LASTEXITCODE -ne 0) {
    Write-WarnMsg "winget install failed for $DisplayName (exit $LASTEXITCODE)"
    return $false
  }
  Write-Ok "$DisplayName installed"
  return $true
}

function Refresh-Path {
  $machine = [Environment]::GetEnvironmentVariable('Path', 'Machine')
  $user = [Environment]::GetEnvironmentVariable('Path', 'User')
  $env:Path = "$machine;$user"
}

function Resolve-Php {
  Refresh-Path
  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  $candidates = @(
    'C:\php\php.exe',
    'C:\tools\php\php.exe',
    "$env:ProgramFiles\PHP\php.exe",
    "${env:ProgramFiles(x86)}\PHP\php.exe"
  ) + @(Get-ChildItem -Path "$env:ProgramFiles" -Filter php.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 5 -ExpandProperty FullName)
  foreach ($c in $candidates) {
    if ($c -and (Test-Path -LiteralPath $c)) { return $c }
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
  $phpIni = & $PhpBin --ini 2>$null | Select-String 'Loaded Configuration File:\s*(.+)$' | ForEach-Object { $_.Matches[0].Groups[1].Value.Trim() }
  if (-not $phpIni -or $phpIni -eq '(none)' -or -not (Test-Path -LiteralPath $phpIni)) {
    Write-WarnMsg "Could not locate php.ini; enable pdo_mysql/curl/gd/mbstring/openssl/zip manually if needed"
    return
  }
  Write-Info "Ensuring common PHP extensions in $phpIni"
  $raw = Get-Content -LiteralPath $phpIni -Raw
  $exts = @('curl', 'fileinfo', 'gd', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'xml', 'zip')
  foreach ($ext in $exts) {
    # Uncomment ";extension=ext" or ";extension=php_ext.dll"
    $raw = [regex]::Replace($raw, "(?im)^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$", "extension=$ext")
    if ($raw -notmatch "(?im)^\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$") {
      $raw += "`r`nextension=$ext"
    }
  }
  Set-Content -LiteralPath $phpIni -Value $raw -Encoding UTF8
  Write-Ok "Updated php.ini extensions (review if PHP fails to start)"
}

function Invoke-MysqlSql {
  param(
    [string]$MysqlBin,
    [string]$Sql,
    [string]$User = 'root',
    [string]$Password = '',
    [string]$Database = ''
  )
  $args = @("-u$user")
  if (-not [string]::IsNullOrEmpty($Password)) { $args += "-p$Password" }
  if ($Database) { $args += $Database }
  $args += @('-e', $Sql)
  & $MysqlBin @args
  if ($LASTEXITCODE -ne 0) { throw "mysql failed: $Sql" }
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
`$dbsettings['host'] = 'localhost';
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
  $hook = @"
<?php
if (file_exists(__DIR__ . '/../../../custom/includes/theme-loader.inc.php')) {
    include_once(__DIR__ . '/../../../custom/includes/theme-loader.inc.php');
}
?>
"@
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
    Invoke-MysqlSql -MysqlBin $MysqlBin -User $User -Password $Pass -Database $Name -Sql `
      "UPDATE usersettings SET otherprefs = REPLACE(otherprefs, 's:6:\"modern\"', 's:6:\"we1rdo\"');"
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
  if (-not (Resolve-Php)) {
    # Common winget ids vary by publisher; try a few
    $ok = $false
    foreach ($id in @('PHP.PHP.8.3', 'PHP.PHP.8.2', 'PHP.PHP')) {
      if (Install-WingetPackage -Id $id -DisplayName "PHP ($id)") { $ok = $true; break }
    }
    if (-not $ok) {
      Write-WarnMsg "Could not install PHP automatically. Install PHP 8.2+ manually, then re-run with -SkipPackageInstall if already present."
    }
    Refresh-Path
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
    Write-WarnMsg "If MariaDB was just installed, start the service from Services.msc (MariaDB) before continuing."
    if (-not $NonInteractive) { Read-Host "Press Enter when MariaDB/MySQL is running" | Out-Null }
  }
}

$PhpBin = Resolve-Php
if (-not $PhpBin) { Die "PHP not found on PATH" }
Write-Ok "Using PHP: $PhpBin"
Enable-PhpExtensions -PhpBin $PhpBin

$MysqlBin = Resolve-Mysql
if (-not $MysqlBin) { Die "mysql client not found on PATH" }
Write-Ok "Using mysql: $MysqlBin"

Write-Info "Creating database and user..."
$rootPass = ''
if (-not $NonInteractive) {
  $rootPass = Read-Host "MariaDB/MySQL root password (blank if none)"
}
try {
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';"
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "ALTER USER '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';"
  Invoke-MysqlSql -MysqlBin $MysqlBin -Password $rootPass -Sql "GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';"
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
