@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\start-public-demo.ps1"
set "BBQ_DEMO_EXIT=%errorlevel%"
echo.
pause
exit /b %BBQ_DEMO_EXIT%
