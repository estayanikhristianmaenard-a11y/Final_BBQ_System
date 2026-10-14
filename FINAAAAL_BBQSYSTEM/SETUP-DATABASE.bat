@echo off
call "%~dp0tools\run-transfer.bat" setup-database.php
set "BBQ_RESULT=%errorlevel%"
echo.
pause
exit /b %BBQ_RESULT%
