@echo off
chcp 65001 >nul
title Laravel Backup Worker — Uninstaller

net session >nul 2>&1
if %errorLevel% neq 0 (
    powershell -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb RunAs"
    exit /b
)

echo Removing scheduled tasks...
schtasks /delete /tn "LaravelQueueWorker_ERP_Q" /f
schtasks /delete /tn "LaravelScheduler_ERP_Q" /f

echo Done.
pause
