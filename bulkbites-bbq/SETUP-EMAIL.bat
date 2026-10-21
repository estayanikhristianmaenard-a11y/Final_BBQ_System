@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\setup-email.ps1"
set "BBQ_SETUP_EXIT=%errorlevel%"
echo.
pause
exit /b %BBQ_SETUP_EXIT%
