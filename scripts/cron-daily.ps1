<#
.SYNOPSIS
    Daily Cron Data Synchronization Script (PowerShell / Windows Task Scheduler)
    Products Analytics Dashboard

.DESCRIPTION
    Runs the automated daily sync command (spark cron:daily), updates snapshots and products,
    and optionally indexes vectors for newly imported products.

.EXAMPLE
    pwsh -File .\scripts\cron-daily.ps1
    pwsh -File .\scripts\cron-daily.ps1 -TargetDate 2026-09-13
#>

param(
    [string]$TargetDate = "",
    [switch]$NoVectorize
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir "..")

Set-Location $ProjectRoot

Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Starting Daily Cron Sync" -ForegroundColor Green
Write-Host "Project Root: $ProjectRoot" -ForegroundColor Gray
Write-Host "======================================================================" -ForegroundColor Cyan

$SparkArgs = @("spark", "cron:daily")

if ($TargetDate -ne "") {
    $SparkArgs += "--date=$TargetDate"
}

try {
    & php $SparkArgs
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Daily Cron completed successfully." -ForegroundColor Green
    } else {
        Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Daily Cron finished with exit code $LASTEXITCODE." -ForegroundColor Yellow
    }
} catch {
    Write-Error "Execution failed: $_"
    exit 1
}
