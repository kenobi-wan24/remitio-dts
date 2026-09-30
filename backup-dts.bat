@echo off
REM Automatic backup - run daily by Windows Task Scheduler.
REM Keeps the 10 most recent automatic backups in storage\app\private\backups
cd /d "%~dp0"

set PHP=php
if exist "C:\xampp\php\php.exe" set PHP=C:\xampp\php\php.exe

"%PHP%" artisan dts:backup --keep=10 >> storage\logs\backup.log 2>&1
