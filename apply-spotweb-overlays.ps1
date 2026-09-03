<#
.SYNOPSIS
  Apply VenimK Spotweb overlays (modern UI fixes, NZBGet panel, router, helpers)
  onto an existing Spotweb install (Windows).

.EXAMPLE
  .\apply-spotweb-overlays.ps1 -SpotwebDir C:\Spotweb
#>
[CmdletBinding()]
param(
  [Parameter(Position = 0)]
  [string]$SpotwebDir = $env:SPOTWEB_DIR,

  [string]$OverlaySrc = $env:OVERLAY_SRC,

  [string]$GithubZipUrl = 'https://codeload.github.com/VenimK/spotweb/zip/refs/heads/themes-only'
)

$ErrorActionPreference = 'Stop'

function Write-Info([string]$Message) { Write-Host "i  $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "OK $Message" -ForegroundColor Green }
function Write-WarnMsg([string]$Message) { Write-Host "!  $Message" -ForegroundColor Yellow }
function Die([string]$Message) { Write-Host "X  $Message" -ForegroundColor Red; exit 1 }

if ([string]::IsNullOrWhiteSpace($SpotwebDir)) {
  Die "Usage: .\apply-spotweb-overlays.ps1 -SpotwebDir C:\path\to\spotweb"
}

$SpotwebDir = (Resolve-Path -LiteralPath $SpotwebDir).Path
if (-not (Test-Path -LiteralPath (Join-Path $SpotwebDir 'index.php'))) {
  Die "Not a Spotweb root (missing index.php): $SpotwebDir"
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$TempRoot = $null

try {
  if (-not [string]::IsNullOrWhiteSpace($OverlaySrc) -and (Test-Path -LiteralPath $OverlaySrc)) {
    $src = (Resolve-Path -LiteralPath $OverlaySrc).Path
  } elseif (Test-Path -LiteralPath (Join-Path $ScriptDir 'overlays\spotweb')) {
    $src = (Resolve-Path -LiteralPath (Join-Path $ScriptDir 'overlays\spotweb')).Path
  } else {
    Write-Info "Downloading overlays from GitHub (themes-only)..."
    $TempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("spotweb-overlays-" + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $TempRoot | Out-Null
    $zipPath = Join-Path $TempRoot 'themes-only.zip'
    Invoke-WebRequest -Uri $GithubZipUrl -OutFile $zipPath -UseBasicParsing
    Expand-Archive -LiteralPath $zipPath -DestinationPath $TempRoot -Force
    $overlayDir = Get-ChildItem -Path $TempRoot -Directory -Recurse -Filter 'overlays' |
      Where-Object { Test-Path (Join-Path $_.FullName 'spotweb') } |
      Select-Object -First 1
    if (-not $overlayDir) { Die "Overlays not found in themes-only archive" }
    $src = Join-Path $overlayDir.FullName 'spotweb'
  }

  Write-Info "Applying overlays from: $src"
  Write-Info "Target Spotweb: $SpotwebDir"

  $copied = 0
  Get-ChildItem -Path $src -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($src.Length).TrimStart('\', '/')
    $dest = Join-Path $SpotwebDir $rel
    $destDir = Split-Path -Parent $dest
    if (-not (Test-Path -LiteralPath $destDir)) {
      New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item -LiteralPath $_.FullName -Destination $dest -Force
    $copied++
  }

  Write-Ok "Applied $copied overlay file(s)"
  Write-Info "Includes: NZBGet panel overlap fix, modern Power UX, router.php, doctor helpers, NZBGet API improvements"
  Write-Host ""
  Write-Host "Start Spotweb on Windows:"
  Write-Host "  .\Start-Spotweb.ps1 -SpotwebDir `"$SpotwebDir`""
  Write-Host "  # or: php -S 127.0.0.1:9999 -t `"$SpotwebDir`" `"$SpotwebDir\router.php`""
}
finally {
  if ($TempRoot -and (Test-Path -LiteralPath $TempRoot)) {
    Remove-Item -LiteralPath $TempRoot -Recurse -Force -ErrorAction SilentlyContinue
  }
}
