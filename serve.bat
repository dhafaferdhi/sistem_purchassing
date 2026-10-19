@echo off
title Laravel Purchasing Server
echo ====================================================
echo Starting Laravel Purchasing Server...
echo ====================================================
set "PATH=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64;C:\laragon\bin\composer;%PATH%"
php artisan serve
pause

