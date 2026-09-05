<#
.SYNOPSIS
  Spotweb Theme & Tools Updater for Windows
  Updates themes, tools, and JS from GitHub (themes-only branch) while preserving custom themes.

.DESCRIPTION
  Windows equivalent of update-themes.sh.
  - Backs up custom themes before updating
  - Downloads latest preinstalled themes, JS, tools, and includes
  - Restores custom themes after update
  - Self-updates

.EXAMPLE
  .\update-themes.ps1
  .\update-themes.ps1 -SpotwebDir C:\inetpub\wwwroot\spotweb
#>
param(
  [string]$SpotwebDir = ""
)

$ErrorActionPreference = "Stop"

$GithubRawBase = "https://raw.githubusercontent.com/VenimK/spotweb/themes-only"

# Determine custom directory
if ($SpotwebDir -ne "") {
  $CustomDir = Join-Path $SpotwebDir "custom"
} else {
  $CustomDir = $PSScriptRoot
  if (-not (Test-Path (Join-Path $CustomDir "themes"))) {
    # Try parent\custom
    $Parent = Split-Path $CustomDir -Parent
    $Candidate = Join-Path $Parent "custom"
    if (Test-Path $Candidate) { $CustomDir = $Candidate }
  }
}

if (-not (Test-Path $CustomDir)) {
  Write-Host "ERROR: Custom directory not found: $CustomDir" -ForegroundColor Red
  Write-Host "Usage: .\update-themes.ps1 -SpotwebDir C:\path\to\spotweb" -ForegroundColor Yellow
  exit 1
}

Write-Host ""
Write-Host "  Updating Spotweb Themes & Tools..." -ForegroundColor Cyan
Write-Host "  Location: $CustomDir"
Write-Host ""

# --- Backup custom themes ---
$ThemesDir = Join-Path $CustomDir "themes"
$BackupDir = Join-Path $env:TEMP "spotweb-custom-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"

$CustomThemes = @()
if (Test-Path $ThemesDir) {
  $CustomThemes = Get-ChildItem -Path $ThemesDir -Filter "theme-*.css" -File -ErrorAction SilentlyContinue
}

Write-Host "  Checking for custom themes..." -ForegroundColor Yellow
if ($CustomThemes.Count -gt 0) {
  Write-Host "    Found $($CustomThemes.Count) custom theme(s):"
  foreach ($t in $CustomThemes) { Write-Host "      - $($t.Name)" }
  Write-Host ""
  Write-Host "  Creating backup..." -ForegroundColor Yellow
  New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
  foreach ($t in $CustomThemes) {
    Copy-Item $t.FullName $BackupDir -Force
  }
  Write-Host "    Backed up to: $BackupDir" -ForegroundColor Green
} else {
  Write-Host "    No custom themes found"
}
Write-Host ""

# --- Helper function ---
function Download-File {
  param([string]$Url, [string]$Dest)
  try {
    Invoke-WebRequest -Uri $Url -OutFile $Dest -UseBasicParsing -ErrorAction Stop
    Write-Host "    OK: $(Split-Path $Dest -Leaf)" -ForegroundColor Green
  } catch {
    Write-Host "    SKIP: $(Split-Path $Dest -Leaf) ($($_.Exception.Message))" -ForegroundColor DarkGray
  }
}

# --- Update preinstalled themes ---
$PreinstalledDir = Join-Path $ThemesDir "preinstalled"
if (-not (Test-Path $PreinstalledDir)) {
  New-Item -ItemType Directory -Path $PreinstalledDir -Force | Out-Null
}

Write-Host "  Downloading latest preinstalled themes..." -ForegroundColor Yellow
$ThemeList = @('dark','midnight-ocean','cyberpunk','nord','dracula','forest','sunset','spring','summer','autumn','winter')
foreach ($theme in $ThemeList) {
  $url = "$GithubRawBase/custom/themes/preinstalled/theme-$theme.css"
  $dest = Join-Path $PreinstalledDir "theme-$theme.css"
  Download-File $url $dest
}
Write-Host ""

# --- Update JavaScript ---
$JsDir = Join-Path $CustomDir "js"
if (-not (Test-Path $JsDir)) {
  New-Item -ItemType Directory -Path $JsDir -Force | Out-Null
}

Write-Host "  Downloading latest JavaScript..." -ForegroundColor Yellow
Download-File "$GithubRawBase/custom/js/theme-switcher.js" (Join-Path $JsDir "theme-switcher.js")
Download-File "$GithubRawBase/custom/js/filter-manager-link.js" (Join-Path $JsDir "filter-manager-link.js")
Write-Host ""

# --- Update tools ---
$ToolsDir = Join-Path $CustomDir "tools"
if (-not (Test-Path $ToolsDir)) {
  New-Item -ItemType Directory -Path $ToolsDir -Force | Out-Null
}

Write-Host "  Downloading latest tools..." -ForegroundColor Yellow
Download-File "$GithubRawBase/custom/tools/theme-customizer.html" (Join-Path $ToolsDir "theme-customizer.html")
Download-File "$GithubRawBase/custom/tools/theme-upload.php" (Join-Path $ToolsDir "theme-upload.php")
Download-File "$GithubRawBase/custom/tools/filter-manager.php" (Join-Path $ToolsDir "filter-manager.php")
Download-File "$GithubRawBase/custom/tools/.htaccess" (Join-Path $ToolsDir ".htaccess")
Write-Host ""

# --- Update includes ---
$IncludesDir = Join-Path $CustomDir "includes"
if (-not (Test-Path $IncludesDir)) {
  New-Item -ItemType Directory -Path $IncludesDir -Force | Out-Null
}

Write-Host "  Downloading latest includes..." -ForegroundColor Yellow
Download-File "$GithubRawBase/custom/includes/theme-loader.inc.php" (Join-Path $IncludesDir "theme-loader.inc.php")
Write-Host ""

# --- Update README ---
Write-Host "  Downloading latest documentation..." -ForegroundColor Yellow
Download-File "$GithubRawBase/custom/README.md" (Join-Path $CustomDir "README.md")
Write-Host ""

# --- Self-update ---
Write-Host "  Self-updating update script..." -ForegroundColor Yellow
Download-File "$GithubRawBase/custom/update-themes.ps1" (Join-Path $CustomDir "update-themes.ps1.new")
$newScript = Join-Path $CustomDir "update-themes.ps1.new"
if (Test-Path $newScript) {
  Move-Item $newScript (Join-Path $CustomDir "update-themes.ps1") -Force
  Write-Host "    OK: update-themes.ps1" -ForegroundColor Green
}
Write-Host ""

# --- Restore custom themes ---
Write-Host "  Restoring custom themes..." -ForegroundColor Yellow
if ($CustomThemes.Count -gt 0 -and (Test-Path $BackupDir)) {
  $Restored = 0
  foreach ($t in $CustomThemes) {
    $backupFile = Join-Path $BackupDir $t.Name
    if (Test-Path $backupFile) {
      Copy-Item $backupFile (Join-Path $ThemesDir $t.Name) -Force
      Write-Host "    Restored: $($t.Name)" -ForegroundColor Green
      $Restored++
    }
  }
  Write-Host ""
  Write-Host "  Restored $Restored/$($CustomThemes.Count) custom theme(s)" -ForegroundColor Green
} else {
  Write-Host "    No custom themes to restore"
}
Write-Host ""

# --- Final verification ---
$PreinstalledCount = (Get-ChildItem -Path $PreinstalledDir -Filter "theme-*.css" -File -ErrorAction SilentlyContinue).Count
$CustomFinal = @()
if (Test-Path $ThemesDir) {
  $CustomFinal = Get-ChildItem -Path $ThemesDir -Filter "theme-*.css" -File -ErrorAction SilentlyContinue
}

Write-Host "  ====================================" -ForegroundColor Cyan
Write-Host "  Update complete!" -ForegroundColor Green
Write-Host "  ====================================" -ForegroundColor Cyan
Write-Host "  Preinstalled themes: $PreinstalledCount"
Write-Host "  Your custom themes:  $($CustomFinal.Count)"
if ($CustomFinal.Count -ne $CustomThemes.Count) {
  Write-Host "  WARNING: Started with $($CustomThemes.Count), ended with $($CustomFinal.Count)" -ForegroundColor Yellow
  Write-Host "  Backup available at: $BackupDir" -ForegroundColor Yellow
}
Write-Host ""
Write-Host "  Refresh your browser to see updates!" -ForegroundColor Cyan
Write-Host ""

# --- Cleanup old backups (keep last 5) ---
Write-Host "  Cleaning up old backups..." -ForegroundColor Yellow
$OldBackups = Get-ChildItem -Path $env:TEMP -Directory -Filter "spotweb-custom-backup-*" -ErrorAction SilentlyContinue |
  Sort-Object Name -Descending | Select-Object -Skip 5
foreach ($b in $OldBackups) {
  Remove-Item $b.FullName -Recurse -Force -ErrorAction SilentlyContinue
  Write-Host "    Removed: $($b.Name)" -ForegroundColor DarkGray
}
Write-Host "  Kept last 5 backups"
Write-Host ""
