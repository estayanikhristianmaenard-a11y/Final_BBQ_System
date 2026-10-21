@echo off
setlocal
call "%~dp0tools\run-transfer.bat" migrate-payments.php
set "BBQ_UPGRADE_EXIT=%errorlevel%"
pause
exit /b %BBQ_UPGRADE_EXIT%
