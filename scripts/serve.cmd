@echo off
setlocal
cd /d "%~dp0.."
set "PHP_INI_SCAN_DIR=%CD%\config\php"
set "OMS_PHP=C:\xampp\php\php.exe"
if not exist "%OMS_PHP%" set "OMS_PHP=php"
"%OMS_PHP%" artisan serve --host=127.0.0.1 --port=8000
endlocal
