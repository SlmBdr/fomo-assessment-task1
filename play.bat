@echo off
set PHP_CMD=

where php >nul 2>nul
if %errorlevel% equ 0 set PHP_CMD=php
if not "%PHP_CMD%"=="" goto RUN_SERVER

echo PHP tidak terdeteksi di PATH system Anda.
set /p USER_PHP="Silakan ketik lokasi file php.exe Anda (contoh: C:\xampp\php\php.exe): "
if not "%USER_PHP%"=="" set PHP_CMD="%USER_PHP%"

if "%PHP_CMD%"=="" (
    echo.
    echo Error: Lokasi PHP tidak ditentukan. Program dibatalkan.
    pause
    exit /b
)

:RUN_SERVER
echo Menggunakan PHP dari: %PHP_CMD%
echo.

if not exist "%~dp0vendor\autoload.php" (
    echo.
    echo Peringatan: Folder 'vendor' belum terinstall.
    echo Menjalankan 'composer install' terlebih dahulu...
    echo.
    where composer >nul 2>nul
    if %errorlevel% equ 0 (
        composer install
    ) else (
        if exist "C:\ProgramData\ComposerSetup\bin\composer.bat" (
            call "C:\ProgramData\ComposerSetup\bin\composer.bat" install
        ) else (
            echo Composer tidak terdeteksi. Silakan jalankan 'composer install' secara manual terlebih dahulu di folder ini.
            pause
            exit /b
        )
    )
)

echo.
echo Menjalankan Laravel Server di http://localhost:8000 ...
echo Silakan buka browser dan akses http://localhost:8000 untuk masuk ke Swagger UI secara otomatis.
echo.
%PHP_CMD% -S localhost:8000 -t "%~dp0public"
pause
