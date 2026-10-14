@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
title Laravel Backup Worker — Auto Installer

REM ═══ طلب صلاحيات Admin تلقائياً ═══
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo.
    echo [INFO] Requesting Administrator privileges...
    timeout /t 2 /nobreak >nul
    powershell -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb RunAs"
    exit /b
)

echo.
echo ============================================================
echo   Laravel Backup Worker - Automatic Installation
echo ============================================================
echo.

REM ═══ اكتشاف مسار المشروع ═══
cd /d "%~dp0.."
set "PROJECT_PATH=%CD%"
echo [1/6] Project path: %PROJECT_PATH%

if not exist "%PROJECT_PATH%\artisan" (
    echo.
    echo [ERROR] artisan not found in %PROJECT_PATH%
    echo         Make sure this installer is inside setup\ folder
    echo         inside the project root.
    echo.
    pause
    exit /b 1
)
echo       OK

REM ═══ اكتشاف PHP ═══
echo.
echo [2/6] Detecting PHP...
set "PHP_PATH="
where php >nul 2>&1
if %errorLevel% equ 0 (
    for /f "delims=" %%i in ('where php 2^>nul') do (
        set "PHP_PATH=%%i"
        goto :php_found
    )
)

if exist "C:\xampp\php\php.exe"      set "PHP_PATH=C:\xampp\php\php.exe"        & goto :php_found
if exist "D:\xampp\php\php.exe"      set "PHP_PATH=D:\xampp\php\php.exe"        & goto :php_found
if exist "C:\laragon\bin\php\php.exe" set "PHP_PATH=C:\laragon\bin\php\php.exe" & goto :php_found
if exist "C:\wamp64\bin\php\php.exe"  set "PHP_PATH=C:\wamp64\bin\php\php.exe"  & goto :php_found

echo.
echo [ERROR] PHP not found in PATH or common locations.
echo         Please add PHP to system PATH, or edit this file.
pause
exit /b 1

:php_found
echo       OK - %PHP_PATH%

REM ═══ إنشاء ملفات Worker ═══
echo.
echo [3/6] Creating worker scripts...
set "WORKER_DIR=%PROJECT_PATH%\storage\app\worker"
if not exist "%WORKER_DIR%" mkdir "%WORKER_DIR%"

REM start-worker.bat
(
echo @echo off
echo title Laravel Queue Worker
echo cd /d "%PROJECT_PATH%"
echo.
echo :wait_mysql
echo ping -n 4 127.0.0.1 ^>nul
echo "%PHP_PATH%" -r "exit(@fsockopen('127.0.0.1', 3306) ? 0 : 1);" 2^>nul
echo if errorlevel 1 goto wait_mysql
echo.
echo :loop
echo echo [%%date%% %%time%%] Starting worker... ^>^> "%PROJECT_PATH%\storage\logs\worker.log"
echo "%PHP_PATH%" artisan queue:work --queue=backups,default --tries=1 --timeout=3600 --max-time=86400 ^>^> "%PROJECT_PATH%\storage\logs\worker.log" 2^>^&1
echo echo [%%date%% %%time%%] Worker stopped. Restarting in 5s... ^>^> "%PROJECT_PATH%\storage\logs\worker.log"
echo ping -n 6 127.0.0.1 ^>nul
echo goto loop
) > "%WORKER_DIR%\start-worker.bat"

REM start-hidden.vbs
(
echo Set FSO = CreateObject("Scripting.FileSystemObject"^)
echo Set WshShell = CreateObject("WScript.Shell"^)
echo scriptDir = FSO.GetParentFolderName(WScript.ScriptFullName^)
echo WshShell.Run "cmd /c """ ^& scriptDir ^& "\start-worker.bat""", 0, False
) > "%WORKER_DIR%\start-hidden.vbs"

REM run-scheduler.bat
(
echo @echo off
echo cd /d "%PROJECT_PATH%"
echo "%PHP_PATH%" artisan schedule:run
) > "%WORKER_DIR%\run-scheduler.bat"

echo       OK

REM ═══ حذف المهام القديمة ═══
echo.
echo [4/6] Removing old tasks (if any)...
schtasks /delete /tn "LaravelQueueWorker_ERP_Q" /f >nul 2>&1
schtasks /delete /tn "LaravelScheduler_ERP_Q" /f >nul 2>&1
echo       OK

REM ═══ تسجيل المهام الجديدة ═══
echo.
echo [5/6] Registering scheduled tasks...

schtasks /create /tn "LaravelQueueWorker_ERP_Q" ^
    /tr "wscript.exe \"%WORKER_DIR%\start-hidden.vbs\"" ^
    /sc onstart /delay 0002:00 ^
    /ru SYSTEM /rl HIGHEST /f >nul 2>&1

if %errorLevel% neq 0 (
    echo.
    echo [ERROR] Failed to register Worker task
    pause
    exit /b 1
)
echo       Worker task OK

schtasks /create /tn "LaravelScheduler_ERP_Q" ^
    /tr "%WORKER_DIR%\run-scheduler.bat" ^
    /sc minute /mo 1 ^
    /ru SYSTEM /rl HIGHEST /f >nul 2>&1

if %errorLevel% neq 0 (
    echo.
    echo [ERROR] Failed to register Scheduler task
    pause
    exit /b 1
)
echo       Scheduler task OK

REM ═══ اختبار سريع ═══
echo.
echo [6/6] Testing installation...
schtasks /run /tn "LaravelQueueWorker_ERP_Q" >nul 2>&1
timeout /t 8 /nobreak >nul

tasklist /FI "IMAGENAME eq php.exe" 2>nul | find "php.exe" >nul
if %errorLevel% equ 0 (
    echo       Worker is running
) else (
    echo       [WARNING] Worker task created but no php.exe found yet
    echo                 It will start at next Windows boot.
)

echo.
echo ============================================================
echo   INSTALLATION COMPLETE
echo ============================================================
echo.
echo   What was installed:
echo     - Worker: starts automatically at Windows boot
echo     - Worker: restarts automatically if it crashes
echo     - Scheduler: runs Laravel tasks every minute
echo.
echo   No further action needed.
echo.
echo   Test now:
echo     1. Open the ERP in browser
echo     2. Go to: Settings ^> System ^> Backup
echo     3. Click "Export Backup"
echo.
pause
