@echo off
REM ==============================================================================
REM Daily Cron Data Synchronization Batch Script (Windows Task Scheduler)
REM Products Analytics Dashboard
REM
REM Usage in Windows Task Scheduler:
REM Action: Start a program
REM Program/script: C:\path\to\project\scripts\cron-daily.bat
REM ==============================================================================

setlocal enabledelayedexpansion

REM Resolve project directory (parent directory of scripts/)
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%..\"

cd /d "%PROJECT_ROOT%"

echo ======================================================================
echo [%date% %time%] Starting Daily Cron Data Sync on Windows
echo Project Root: %CD%
echo ======================================================================

REM Check PHP availability
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP executable was not found in PATH!
    exit /b 1
)

php spark cron:daily --vectorize %*

if %errorlevel% equ 0 (
    echo [%date% %time%] Daily Cron completed successfully.
) else (
    echo [%date% %time%] Daily Cron completed with errors (Exit code: %errorlevel%).
)

exit /b %errorlevel%
