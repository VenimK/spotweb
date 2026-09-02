<#
.SYNOPSIS
  Start Spotweb with PHP's built-in server and the caching router (Windows).

.EXAMPLE
  .\Start-Spotweb.ps1
  .\Start-Spotweb.ps1 -SpotwebDir C:\Spotweb -Port 9999
#>
[CmdletBinding()]
param(
  [string]$SpotwebDir = $(if ($PSScriptRoot) { $PSScriptRoot } else { Get-Location }),
  [string]$HostAddress = '127.0.0.1',
  [int]$Port = 9999,
  [string]$PhpBin = ''
)

$ErrorActionPreference = 'Stop'

function Resolve-Php {
  param([string]$Preferred)
  if ($Preferred -and (Test-Path -LiteralPath $Preferred)) { return (Resolve-Path $Preferred).Path }
  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }
  $candidates = @(
    'C:\php\php.exe',
    'C:\tools\php\php.exe',
    "$env:ProgramFiles\PHP\php.exe",
    "${env:ProgramFiles(x86)}\PHP\php.exe"
  )
  foreach ($c in $candidates) {
    if (Test-Path -LiteralPath $c) { return $c }
  }
  throw "PHP not found. Install PHP 8.2+ and ensure php.exe is on PATH."
}

if (-not (Test-Path -LiteralPath (Join-Path $SpotwebDir 'index.php'))) {
  # If started from themes-only checkout, Spotweb may live elsewhere
  if (Test-Path -LiteralPath (Join-Path $env:USERPROFILE 'Spotweb\index.php')) {
    $SpotwebDir = Join-Path $env:USERPROFILE 'Spotweb'
  } else {
    throw "Spotweb not found at '$SpotwebDir'. Pass -SpotwebDir."
  }
}

$SpotwebDir = (Resolve-Path -LiteralPath $SpotwebDir).Path
$php = Resolve-Php -Preferred $PhpBin
$router = Join-Path $SpotwebDir 'router.php'

Write-Host "Spotweb directory : $SpotwebDir"
Write-Host "PHP               : $php"
Write-Host "URL               : http://${HostAddress}:${Port}/"
if (Test-Path -LiteralPath $router) {
  Write-Host "Router            : $router"
  Write-Host ""
  Write-Host "Press Ctrl+C to stop."
  Set-Location -LiteralPath $SpotwebDir
  & $php -S "${HostAddress}:${Port}" -t $SpotwebDir $router
} else {
  Write-Host "Router            : (missing — running without caching router)"
  Write-Host ""
  Write-Host "Press Ctrl+C to stop."
  Set-Location -LiteralPath $SpotwebDir
  & $php -S "${HostAddress}:${Port}" -t $SpotwebDir
}
