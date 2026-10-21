@echo off
setlocal
if "%~1"=="" exit /b 1
set "BBQ_PHP="
set "BBQ_SEARCH=%~dp0"
for /l %%N in (1,1,6) do call :find_php
if not defined BBQ_PHP if exist "C:\xampp\php\php.exe" set "BBQ_PHP=C:\xampp\php\php.exe"
if not defined BBQ_PHP if exist "D:\xampp\php\php.exe" set "BBQ_PHP=D:\xampp\php\php.exe"
if not defined BBQ_PHP if exist "E:\xampp\php\php.exe" set "BBQ_PHP=E:\xampp\php\php.exe"
if defined BBQ_PHP goto run
where php >nul 2>nul
if errorlevel 1 (
    echo Could not find PHP. Put the repository inside XAMPP's htdocs folder.
    exit /b 1
)
set "BBQ_PHP=php"
:run
"%BBQ_PHP%" "%~dp0%~1"
exit /b %errorlevel%
:find_php
if defined BBQ_PHP exit /b
if exist "%BBQ_SEARCH%php\php.exe" (
    set "BBQ_PHP=%BBQ_SEARCH%php\php.exe"
    exit /b
)
for %%D in ("%BBQ_SEARCH%..") do set "BBQ_SEARCH=%%~fD\"
exit /b
