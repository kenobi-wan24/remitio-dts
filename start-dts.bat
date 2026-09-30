@echo off
title Remitio DTS - Server (keep this window open)
cd /d "%~dp0"

set PHP=php
if exist "C:\xampp\php\php.exe" set PHP=C:\xampp\php\php.exe

echo ============================================================
echo   Remitio ^& Remitio Law Offices - Document Tracking System
echo ============================================================
echo.
echo   This computer:      http://localhost:8000
echo   Other office PCs:   http://%COMPUTERNAME%:8000
echo                       (or this PC's IP address - run "ipconfig")
echo.
echo   Make sure MySQL is running in the XAMPP Control Panel.
echo   KEEP THIS WINDOW OPEN while the system is in use.
echo ============================================================
echo.

"%PHP%" artisan serve --host=0.0.0.0 --port=8000
pause
