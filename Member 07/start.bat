@echo off
REM Runs the site with PHP's built-in server (uses XAMPP's php.exe if php is not in PATH)
cd /d "%~dp0"
where php >nul 2>nul && (php -S localhost:8000 -t . router.php) || (C:\xampp\php\php.exe -S localhost:8000 -t . router.php)
