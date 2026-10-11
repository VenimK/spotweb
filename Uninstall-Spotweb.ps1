# Spotweb Windows uninstaller v1.0.0 (Windows PowerShell 5.1 compatible, ASCII-only)
<#
.SYNOPSIS
  Remove Spotweb and everything Install-Spotweb.ps1 (v2.2.12) created.

.DESCRIPTION
  Reverse-engineered footprint of Install-Spotweb.ps1 + Start-Spotweb.ps1:

    1. Runtime          php -S 127.0.0.1:<Port> (php.exe / php-cgi.exe process),
                        optional mysqld.exe started by the installer.
    2. Database         CREATE DATABASE <DbName>, CREATE USER <DbUser>@localhost
                        and <DbUser>@127.0.0.1, GRANT ALL, FLUSH PRIVILEGES,
                        bin/upgrade-db.php schema init + admin password reset.
    3. Windows service  mysqld --install (Ensure-MariaDbWindowsService) + Start-Service.
    4. Toolchains       %LOCALAPPDATA%\SpotwebTools\php   (portable PHP NTS x64 zip)
                        %LOCALAPPDATA%\SpotwebTools\mariadb (portable MariaDB zip)
                        %LOCALAPPDATA%\SpotwebTools\apache + cache\ (IIS/FastCGI path)
    5. Persistent PATH  Add-ToUserPath: SpotwebTools\php, SpotwebTools\mariadb\bin,
                        winget PHP package folders.
    6. App files        git clone --depth 1 of spotweb/spotweb into <SpotwebDir>,
                        cache\, custom\ themes, patched templates\*\includes\header.inc.php,
                        dbsettings.inc.php, ownsettings.php, .rnd, Start-Spotweb.ps1 copy.
    7. IIS path         appcmd site + apppool, fastCgi registration, web.config,
                        hosts entry, URL Rewrite MSI, ACL grants on cache\ and root.
    8. winget packages  Git.Git, PHP.PHP.NTS.8.3/8.4/8.2, MariaDB.Server / Oracle.MySQL,
                        Microsoft.VCRedist.2015+.x64.

  Default behaviour (safe): stop processes only. Everything destructive is opt-in:

    -RemoveSpotwebDir   delete the Spotweb application folder
    -DropDatabase       DROP DATABASE + DROP USER (use -BackupDatabase first)
    -RemoveToolchain    delete %LOCALAPPDATA%\SpotwebTools
    -CleanPath          remove SpotwebTools entries from the persisted User PATH
    -RemoveMysqlService unregister the MariaDB/MySQL Windows service (mysqld --remove)
    -RemoveWingetPkgs   winget uninstall PHP / MariaDB packages
    -RemoveIIS          remove IIS site, app pool, fastCgi handler, web.config, hosts entry

  Idempotent - safe to re-run. Missing pieces are skipped. Use -DryRun (or -WhatIf)
  to see the plan without changing anything.

.PARAMETER SpotwebDir
  Application folder. Default: this script's folder if it looks like a Spotweb root,
  otherwise %USERPROFILE%\Spotweb.

.PARAMETER DbName / DbUser / DbPass
  Database and user created by the installer. Defaults: spotweb / spotweb / spotweb.

.PARAMETER RootPass
  MariaDB/MySQL root password. Blank if none.

.EXAMPLE
  # Show the footprint, change nothing
  .\Uninstall-Spotweb.ps1 -DryRun

.EXAMPLE
  # Full removal, with a mysqldump backup before the drop
  .\Uninstall-Spotweb.ps1 -BackupDatabase -DropDatabase -RemoveSpotwebDir -RemoveToolchain -CleanPath -Force

.EXAMPLE
  # Keep the app folder and the data, only clean toolchain + PATH
  .\Uninstall-Spotweb.ps1 -RemoveToolchain -CleanPath
#>

#Requires -Version 5.1

[CmdletBinding(SupportsShouldProcess)]
param(
  [string]$SpotwebDir = '',
  [string]$DbName = 'spotweb',
  [string]$DbUser = 'spotweb',
  [string]$DbPass = 'spotweb',
  [string]$RootPass = '',
  [int]$Port = 9999,
  [string]$MysqlBin = '',
  [string]$BackupTo = '',
  [switch]$DryRun,
  [switch]$Force,
  [switch]$NonInteractive,
  [switch]$NoElevate,
  [switch]$StopRuntime,
  [switch]$DropDatabase,
  [switch]$BackupDatabase,
  [switch]$RemoveSpotwebDir,
  [switch]$RemoveToolchain,
  [switch]$CleanPath,
  [switch]$RemoveMysqlService,
  [switch]$RemoveWingetPkgs,
  [switch]$RemoveIIS
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

if ($DryRun) { $WhatIfPreference = $true }

$ScriptPath = $MyInvocation.MyCommand.Path
if (-not $ScriptPath) { $ScriptPath = $PSCommandPath }
$ScriptDir = Split-Path -Parent $ScriptPath
$Cmdlet = $PSCmdlet

# ---------------------------------------------------------------------------
# Auto-elevate (service removal, IIS, persisted PATH)
# ---------------------------------------------------------------------------
function Test-IsAdmin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  return ([Security.Principal.WindowsPrincipal]$id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

$needsAdmin = ($RemoveMysqlService -or $RemoveIIS -or $CleanPath -or $DropDatabase)

if (-not (Test-IsAdmin) -and -not $NoElevate -and -not $DryRun) {
  Write-Host 'Requesting Administrator privileges (UAC) ...' -ForegroundColor Yellow
  $argList = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$ScriptPath`"")
  foreach ($sw in @('DryRun','Force','NonInteractive','NoElevate','StopRuntime','DropDatabase','BackupDatabase','RemoveSpotwebDir','RemoveToolchain','CleanPath','RemoveMysqlService','RemoveWingetPkgs','RemoveIIS')) {
    if ($PSBoundParameters.ContainsKey($sw)) { $argList += "-$sw" }
  }
  foreach ($p in @('SpotwebDir','DbName','DbUser','DbPass','RootPass','Port','MysqlBin','BackupTo')) {
    if ($PSBoundParameters.ContainsKey($p)) { $argList += "-$p"; $argList += "`"$($PSBoundParameters[$p])`"" }
  }
  if ($WhatIfPreference) { $argList += '-DryRun' }
  try {
    Start-Process -FilePath 'powershell.exe' -ArgumentList ($argList -join ' ') -Verb RunAs -Wait
  } catch {
    Write-Host '  [ERROR] UAC elevation declined. Re-run as Administrator, or use -NoElevate.' -ForegroundColor Red
    exit 1
  }
  exit 0
}

function Write-Step($msg) { Write-Host "`n=== $msg ===" -ForegroundColor Cyan }
function Write-Ok($msg)    { Write-Host "  [OK]   $msg" -ForegroundColor Green }
function Write-Skip($msg)  { Write-Host "  [SKIP] $msg" -ForegroundColor Yellow }
function Write-Warn($msg)  { Write-Host "  [WARN] $msg" -ForegroundColor Yellow }
function Write-Err($msg)   { Write-Host "  [ERROR] $msg" -ForegroundColor Red }
function Write-Info($msg)  { Write-Host "  $msg" -ForegroundColor DarkGray }

function Confirm-Action {
  param([string]$Message)
  if ($Force -or $NonInteractive) { return $true }
  $r = Read-Host "$Message [y/N]"
  return ($r -match '^[Yy](es)?$')
}

function Test-SpotwebRoot($Path) {
  if (-not $Path) { return $false }
  return (Test-Path -LiteralPath (Join-Path $Path 'index.php')) -and
         (Test-Path -LiteralPath (Join-Path $Path 'settings.php'))
}

function Get-FolderSize($Path) {
  if (-not (Test-Path -LiteralPath $Path)) { return $null }
  $sum = (Get-ChildItem -LiteralPath $Path -Recurse -Force -File -ErrorAction SilentlyContinue |
          Measure-Object -Property Length -Sum)
  return @{ Files = [int]$sum.Count; MB = [math]::Round(($sum.Sum / 1MB), 1) }
}

function Remove-PathSafe {
  param([string]$Path, [string]$Label = '')
  if (-not $Path) { return }
  $label = if ($Label) { $Label } else { $Path }
  if (-not (Test-Path -LiteralPath $Path)) { Write-Skip "not found: $label"; return }
  if (-not $Cmdlet.ShouldProcess($Path, 'Delete folder')) { return }
  try {
    Remove-Item -LiteralPath $Path -Recurse -Force -ErrorAction Stop
    Write-Ok "removed $label"
  } catch {
    Write-Warn "could not remove $label : $($_.Exception.Message)"
  }
}

function Resolve-MysqlBin {
  param([string]$Preferred)
  if ($Preferred -and (Test-Path -LiteralPath $Preferred)) { return (Resolve-Path -LiteralPath $Preferred).Path }
  $portable = Join-Path $env:LOCALAPPDATA 'SpotwebTools\mariadb\bin\mysql.exe'
  if (Test-Path -LiteralPath $portable) { return $portable }
  $cmd = Get-Command mysql -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  $cmd = Get-Command mariadb -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  foreach ($root in @($env:ProgramFiles, ${env:ProgramFiles(x86)})) {
    if (-not (Test-Path -LiteralPath $root)) { continue }
    $dirs = @(Get-ChildItem -LiteralPath $root -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '^(MariaDB|MySQL)' })
    foreach ($d in $dirs) {
      $hit = Get-ChildItem -LiteralPath $d.FullName -Filter 'mysql.exe' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
      if ($hit) { return $hit.FullName }
    }
  }
  return $null
}

function Invoke-MysqlSql {
  param([string]$MysqlBin, [string]$Sql, [string]$User = 'root', [string]$Password = '')
  $args = @('--host=127.0.0.1', '--protocol=TCP', "--user=$User")
  if ($Password) { $args += "-p$Password" }
  $args += @('-e', $Sql)
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'SilentlyContinue'
  try {
    $out = & $MysqlBin @args 2>&1 | Out-String
    $code = $LASTEXITCODE
  } finally { $ErrorActionPreference = $oldEap }
  if ($code -ne 0) { throw "mysql failed ($code): $Sql`n$($out.Trim())" }
  return ($out.Trim())
}

function Get-RowCount {
  param([string]$MysqlBin, [string]$Db, [string]$Table, [string]$User, [string]$Password)
  $out = Invoke-MysqlSql -MysqlBin $MysqlBin -User $User -Password $Password -Sql "SELECT COUNT(*) FROM ``$Db``.``$Table``;"
  return (($out -split "`r?`n" | Select-Object -Last 1).Trim())
}

function Get-PathParts([string]$PathValue) {
  if ([string]::IsNullOrWhiteSpace($PathValue)) { return @() }
  return @($PathValue.Split(';') | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
}

# ---------------------------------------------------------------------------
# Resolve the application directory
# ---------------------------------------------------------------------------
if (-not $SpotwebDir) {
  if (Test-SpotwebRoot $ScriptDir) {
    $SpotwebDir = $ScriptDir
  } else {
    $SpotwebDir = Join-Path $env:USERPROFILE 'Spotweb'
  }
}
$SpotwebDir = [System.IO.Path]::GetFullPath($SpotwebDir)

$ToolchainRoot = Join-Path $env:LOCALAPPDATA 'SpotwebTools'
$MysqlBin = Resolve-MysqlBin -Preferred $MysqlBin
$AppSize = Get-FolderSize $SpotwebDir
$ToolSize = Get-FolderSize $ToolchainRoot

Write-Host ''
Write-Host 'Spotweb Windows uninstaller' -ForegroundColor Green
Write-Host '====================================='
Write-Host "  App folder:        $SpotwebDir"
if ($AppSize) { Write-Host ("  App size:          {0} files, {1} MB" -f $AppSize.Files, $AppSize.MB) } else { Write-Host '  App size:          folder not found' }
Write-Host "  Toolchain:         $ToolchainRoot"
if ($ToolSize) { Write-Host ("  Toolchain size:    {0} files, {1} MB" -f $ToolSize.Files, $ToolSize.MB) } else { Write-Host '  Toolchain size:    folder not found' }
Write-Host "  Database:          $DbName (user $DbUser)"
Write-Host "  mysql client:      $(if ($MysqlBin) { $MysqlBin } else { 'not found' })"
Write-Host ''
Write-Host '  Destructive switches (opt-in):' -ForegroundColor DarkGray
foreach ($sw in @('StopRuntime','BackupDatabase','DropDatabase','RemoveSpotwebDir','RemoveToolchain','CleanPath','RemoveMysqlService','RemoveWingetPkgs','RemoveIIS')) {
  $on = [bool](Get-Variable -Name $sw -ValueOnly)
  Write-Host ("    -{0,-17} {1}" -f $sw, $(if ($on) { 'YES' } else { 'no' }))
}
Write-Host ''

# ---------------------------------------------------------------------------
# Phase 1 - runtime processes
# ---------------------------------------------------------------------------
Write-Step 'Phase 1: Stop running processes'

$phpProcs = @(Get-Process php,php-cgi,php-win -ErrorAction SilentlyContinue)

if ($phpProcs.Count -eq 0) {
  Write-Skip 'no PHP process running'
} elseif ($StopRuntime) {
  foreach ($p in $phpProcs) {
    if (-not $Cmdlet.ShouldProcess("pid $($p.Id)", 'Stop process')) { continue }
    Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue
    Write-Ok "stopped $($p.Name) pid $($p.Id)"
  }
} else {
  Write-Warn "$($phpProcs.Count) PHP process(es) running; pass -StopRuntime to stop them"
  foreach ($p in $phpProcs) { Write-Info "  pid $($p.Id): $($p.Name)" }
}

if (-not $DryRun) {
  $listen = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
  if ($listen) { Write-Info "  port $Port still listening (pid $($listen[0].OwningProcess))" }
}

# mysqld started by the installer (--standalone, no service)
$mysqldProcs = @(Get-Process mysqld, mariadbd -ErrorAction SilentlyContinue)
if ($mysqldProcs.Count -gt 0) {
  Write-Info "  mysqld running (pid $($mysqldProcs[0].Id)) - check whether this is the Windows service or the installer's standalone instance"
}

# ---------------------------------------------------------------------------
# Phase 2 - database
# ---------------------------------------------------------------------------
Write-Step 'Phase 2: Database'

if (-not $MysqlBin) {
  Write-Skip 'no mysql client found - database cannot be inspected or dropped'
} else {
  try {
    $dbs = Invoke-MysqlSql -MysqlBin $MysqlBin -Sql "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DbName';"
    if ($dbs -notmatch "$DbName") {
      Write-Skip "database '$DbName' does not exist"
    } else {
      $statsLine = ((Invoke-MysqlSql -MysqlBin $MysqlBin -Sql "SELECT COUNT(*), ROUND(SUM(data_length+index_length)/1048576,1) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DbName';") -split "`r?`n" | Select-Object -Last 1)
      Write-Info "  $DbName : $statsLine (tables, MB)"
      foreach ($tbl in @('spots','users','settings')) {
        try {
          Write-Info "    $tbl rows: $(Get-RowCount -MysqlBin $MysqlBin -Db $DbName -Table $tbl -User $DbUser -Password $DbPass)"
        } catch {
          try {
            Write-Info "    $tbl rows: $(Get-RowCount -MysqlBin $MysqlBin -Db $DbName -Table $tbl -User root -Password $RootPass)"
          } catch { Write-Warn "could not count $tbl : $($_.Exception.Message)" }
        }
      }

      if ($BackupDatabase) {
        $outDir = if ($BackupTo) { $BackupTo } else { Join-Path $env:USERPROFILE 'Spotweb-backup' }
        $sqlFile = Join-Path $outDir ("{0}-{1}.sql" -f $DbName, (Get-Date -Format 'yyyyMMdd-HHmmss'))
        if (-not (Test-Path -LiteralPath $outDir)) {
          if ($Cmdlet.ShouldProcess($outDir, 'Create folder')) { New-Item -ItemType Directory -Path $outDir -Force | Out-Null }
        }
        if ($Cmdlet.ShouldProcess($sqlFile, 'Write mysqldump')) {
          $bargs = @('--host=127.0.0.1', '--protocol=TCP', '--user=root', '--default-character-set=utf8mb4', '--result-file=' + $sqlFile, $DbName)
          if ($RootPass) { $bargs += "-p$RootPass" }
          $oldEap = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
          & $MysqlBin @bargs 2>&1 | Out-Null
          $code = $LASTEXITCODE
          $ErrorActionPreference = $oldEap
          if ($code -eq 0) { Write-Ok "backup written: $sqlFile" } else { Write-Warn "mysqldump failed (exit $code)" }
        }
      }

      if ($DropDatabase) {
        Write-Warn "This will DESTROY all Spotweb data in '$DbName'."
        if (-not (Confirm-Action "Drop database '$DbName'?")) {
          Write-Host '  Database drop skipped.'
        } else {
          if ($Cmdlet.ShouldProcess($DbName, 'Drop database')) {
            Invoke-MysqlSql -MysqlBin $MysqlBin -Sql "DROP DATABASE IF EXISTS ``$DbName``;"
            Write-Ok "dropped database $DbName"
          }
          foreach ($h in @('localhost', '127.0.0.1')) {
            if ($Cmdlet.ShouldProcess("$DbUser@$h", 'Drop user')) {
              try { Invoke-MysqlSql -MysqlBin $MysqlBin -Sql "DROP USER IF EXISTS '$DbUser'@'$h';" } catch { Write-Warn "drop user $DbUser@$h : $($_.Exception.Message)" }
            }
          }
          try { Invoke-MysqlSql -MysqlBin $MysqlBin -Sql 'FLUSH PRIVILEGES;' } catch { }
        }
      } else {
        Write-Skip "database '$DbName' kept (pass -DropDatabase, or -BackupDatabase to dump it first)"
      }
    }
  } catch {
    Write-Warn "database phase skipped: $($_.Exception.Message)"
  }
}

# ---------------------------------------------------------------------------
# Phase 3 - application files
# ---------------------------------------------------------------------------
Write-Step 'Phase 3: Application folder'

# The installer clones into %USERPROFILE%\Spotweb; Start-Spotweb.ps1 falls back to it too.
$trees = @($SpotwebDir, (Join-Path $env:USERPROFILE 'Spotweb'))
$trees = @($trees | Where-Object { Test-SpotwebRoot $_ } | Select-Object -Unique)

if ($trees.Count -eq 0) {
  Write-Skip 'no Spotweb application folder found'
} else {
  foreach ($t in $trees) {
    $size = Get-FolderSize $t
    Write-Info ("  {0} - {1} files, {2} MB" -f $t, $size.Files, $size.MB)
    if (-not $RemoveSpotwebDir) { continue }

    Write-Warn "This will DELETE: $t"
    Write-Host '  Contains secrets: .rnd, dbsettings.inc.php, ownsettings.php' -ForegroundColor DarkGray
    if (-not (Confirm-Action 'Delete this Spotweb folder?')) { continue }

    $selfInTree = $false
    try {
      $here = [IO.Path]::GetFullPath($ScriptDir).TrimEnd('\')
      $root = [IO.Path]::GetFullPath($t).TrimEnd('\')
      if ($here -ieq $root -or $here.StartsWith($root + '\', [StringComparison]::OrdinalIgnoreCase)) { $selfInTree = $true }
    } catch { }

    if ($selfInTree -and -not $DryRun) {
      # Cannot reliably delete the folder this script lives in mid-run.
      $bat = Join-Path $env:TEMP 'spotweb-remove-app.cmd'
      $lines = @('@echo off', 'timeout /t 2 /nobreak >nul', "rmdir /s /q `"$t`"",
                 "if exist `"$t`" (echo FAILED to remove $t) else (echo Removed $t)", 'del "%~f0"')
      Set-Content -Path $bat -Value $lines -Encoding ASCII
      Start-Process -FilePath 'cmd.exe' -ArgumentList "/c `"$bat`"" -WindowStyle Hidden
      Write-Ok "scheduled delete of $t (this script lives inside it)"
    } else {
      Remove-PathSafe $t 'app folder'
    }
  }
  if (-not $RemoveSpotwebDir) { Write-Skip 'app folders kept (pass -RemoveSpotwebDir to delete)' }
}

# ---------------------------------------------------------------------------
# Phase 4 - toolchains
# ---------------------------------------------------------------------------
Write-Step 'Phase 4: Portable toolchains (%LOCALAPPDATA%\SpotwebTools)'

if ($RemoveToolchain) {
  Remove-PathSafe $ToolchainRoot 'toolchain root'
} else {
  Write-Skip 'toolchain kept (pass -RemoveToolchain to delete php / mariadb / apache / cache)'
}

# ---------------------------------------------------------------------------
# Phase 5 - persisted User PATH
# ---------------------------------------------------------------------------
Write-Step 'Phase 5: Persistent User PATH'

if ($CleanPath) {
  $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
  $parts = Get-PathParts $userPath
  $hits = @($parts | Where-Object { $_ -match 'SpotwebTools' })
  if ($hits.Count -eq 0) {
    Write-Skip 'no SpotwebTools entries in the User PATH'
  } else {
    foreach ($h in $hits) { Write-Info "  removing: $h" }
    if ($Cmdlet.ShouldProcess('User PATH', 'Remove SpotwebTools entries')) {
      $new = @($parts | Where-Object { $_ -notmatch 'SpotwebTools' })
      [Environment]::SetEnvironmentVariable('Path', ($new -join ';'), 'User')
      Write-Ok 'User PATH updated - open a new shell for it to take effect'
    }
  }
} else {
  Write-Skip 'PATH kept (pass -CleanPath to strip SpotwebTools entries)'
}

# ---------------------------------------------------------------------------
# Phase 6 - Windows service (installer ran: mysqld --install + Start-Service)
# ---------------------------------------------------------------------------
Write-Step 'Phase 6: MariaDB/MySQL service'

$svc = @(Get-Service -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '(?i)maria|mysql' -or $_.DisplayName -match '(?i)MariaDB|MySQL' })
if ($svc.Count -eq 0) {
  Write-Skip 'no MariaDB/MySQL service registered'
} else {
  foreach ($s in $svc) { Write-Info "  $($s.Name) - $($s.Status) / $($s.StartType)" }
  if ($RemoveMysqlService) {
    Write-Warn 'Removing the service deletes its registration. Data files are not deleted by this.'
    if (Confirm-Action 'Unregister the MariaDB/MySQL service?') {
      foreach ($s in $svc) {
        $mysqld = $null
        $qc = (& sc.exe qc $s.Name 2>&1 | Out-String)
        $m = [regex]::Match($qc, 'BINARY_PATH_NAME\s+:\s*(.+)', 'IgnoreCase')
        if ($m.Success) {
          $bin = $m.Groups[1].Value.Trim()
          if ($bin -match '^"([^"]+)"') { $mysqld = $matches[1] } else { $mysqld = ($bin -split '\s')[0] }
        }
        if (-not $mysqld -or -not (Test-Path -LiteralPath $mysqld)) {
          Write-Skip "mysqld.exe path not resolved for $($s.Name) - do it manually: sc delete $($s.Name)"
          continue
        }
        Write-Info "  $($s.Name) binary: $mysqld"
        if ($Cmdlet.ShouldProcess($s.Name, 'Remove service')) {
          $oldEap = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
          & $mysqld --remove 2>&1 | Out-Host
          $ErrorActionPreference = $oldEap
          Write-Ok "service registration removed: $($s.Name)"
        }
      }
    }
  } else {
    Write-Skip 'service kept (pass -RemoveMysqlService to unregister it)'
  }
}

# ---------------------------------------------------------------------------
# Phase 7 - IIS leftovers (Configure-Spotweb-IIS.ps1)
# ---------------------------------------------------------------------------
Write-Step 'Phase 7: IIS / FastCGI'

$appcmd = Join-Path $env:SystemRoot 'System32\inetsrv\appcmd.exe'
if (-not (Test-Path -LiteralPath $appcmd)) {
  Write-Skip 'IIS not installed - nothing to remove'
} elseif (-not $RemoveIIS) {
  Write-Skip 'IIS config kept (pass -RemoveIIS to remove site, app pool, fastCgi handler, web.config, hosts entry)'
} else {
  if ($Cmdlet.ShouldProcess('Spotweb IIS site + app pool', 'Remove')) {
    & $appcmd list site Spotweb 2>&1 | Out-Host
    & $appcmd delete site Spotweb 2>&1 | Out-Host
    & $appcmd delete apppool Spotweb 2>&1 | Out-Host
  }
  $cgi = Join-Path $ToolchainRoot 'php\php-cgi.exe'
  if ($Cmdlet.ShouldProcess('fastCgi handler', 'Remove')) {
    & $appcmd set config /section:system.webServer/fastCgi "/+[fullPath='$($cgi -replace '\\','/')']" /remove 2>&1 | Out-Host
  }
  $wc = Join-Path $SpotwebDir 'web.config'
  if (Test-Path -LiteralPath $wc) {
    Write-Warn "web.config was written by the IIS script: $wc"
    Write-Skip 'web.config removal follows the app folder decision above'
  }
  $hosts = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
  if (Test-Path -LiteralPath $hosts) {
    $hit = Select-String -Path $hosts -Pattern '^\s*127\.0\.0\.1\s+Spotweb\s*$' -ErrorAction SilentlyContinue
    if ($hit) {
      if ($Cmdlet.ShouldProcess($hosts, 'Remove Spotweb hosts entry')) {
        (Get-Content -LiteralPath $hosts -ErrorAction SilentlyContinue | Where-Object { $_ -notmatch '^\s*127\.0\.0\.1\s+Spotweb\s*$' }) |
          Set-Content -LiteralPath $hosts -Encoding ASCII
        Write-Ok 'hosts entry removed'
      }
    } else { Write-Skip 'no Spotweb entry in hosts' }
  }
}

# ---------------------------------------------------------------------------
# Phase 8 - winget packages
# ---------------------------------------------------------------------------
Write-Step 'Phase 8: winget packages'

if (-not $RemoveWingetPkgs) {
  Write-Skip 'packages kept (Git, PHP, MariaDB, VCRedist are shared - pass -RemoveWingetPkgs to uninstall)'
} elseif (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
  Write-Skip 'winget not on PATH'
} else {
  foreach ($id in @('PHP.PHP.NTS.8.3','PHP.PHP.NTS.8.4','PHP.PHP.NTS.8.2','PHP.PHP.8.3','PHP.PHP.8.4','PHP.PHP.8.2','MariaDB.Server','Oracle.MySQL')) {
    if (-not $Cmdlet.ShouldProcess($id, 'winget uninstall')) { continue }
    $oldEap = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    & winget uninstall --id $id -e --accept-source-agreements 2>&1 | Out-Host
    $ErrorActionPreference = $oldEap
    if ($LASTEXITCODE -eq 0) { Write-Ok "uninstalled $id" } else { Write-Skip "$id not removed (exit $LASTEXITCODE / not installed)" }
  }
}

# ---------------------------------------------------------------------------
# Phase 9 - loose downloads / shortcuts
# ---------------------------------------------------------------------------
Write-Step 'Phase 9: Temp downloads and shortcuts'

foreach ($f in @((Join-Path ([System.IO.Path]::GetTempPath()) 'rewrite_amd64.msi'))) {
  if (Test-Path -LiteralPath $f) { Remove-PathSafe $f 'temp download' } else { Write-Skip "not found: $f" }
}
Get-ChildItem ([System.IO.Path]::GetTempPath()) -Filter 'apply-spotweb-overlays-*.ps1' -ErrorAction SilentlyContinue |
  ForEach-Object { Remove-PathSafe $_.FullName 'overlay script copy' }

foreach ($d in @((Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs'), (Join-Path $env:ProgramData 'Microsoft\Windows\Start Menu\Programs'), "$env:USERPROFILE\Desktop")) {
  if (-not (Test-Path -LiteralPath $d)) { continue }
  Get-ChildItem -LiteralPath $d -Recurse -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -match 'Spotweb' } |
    ForEach-Object { Remove-PathSafe $_.FullName 'Spotweb shortcut' }
}

$task = Get-ScheduledTask -ErrorAction SilentlyContinue | Where-Object { $_.TaskName -match 'spotweb' -or $_.Actions.Execute -match 'spotweb' }
if ($task) {
  foreach ($t in $task) {
    if ($Cmdlet.ShouldProcess($t.TaskName, 'Unregister task')) {
      Unregister-ScheduledTask -TaskName $t.TaskName -Confirm:$false
      Write-Ok "removed scheduled task $($t.TaskName)"
    }
  }
} else { Write-Skip 'no Spotweb scheduled task' }

Write-Step 'Done'
Write-Host ''
Write-Host '  Kept by default: Git, PHP, MariaDB/MySQL, Visual C++ Redistributable.' -ForegroundColor DarkGray
Write-Host '  Reinstall:  Install-Spotweb.ps1' -ForegroundColor White
Write-Host ''
