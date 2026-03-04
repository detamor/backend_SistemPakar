@echo off
echo ========================================
echo Install Ngrok Authtoken
echo ========================================
echo.
echo Masukkan authtoken Anda (paste dengan Ctrl+V):
echo.

cd /d "C:\Users\ASUS\Downloads\ngrok-v3-stable-windows-amd64"

set /p AUTHTOKEN="Authtoken: "

if "%AUTHTOKEN%"=="" (
    echo.
    echo ERROR: Authtoken tidak boleh kosong!
    pause
    exit /b 1
)

echo.
echo Menginstall authtoken...
ngrok.exe config add-authtoken %AUTHTOKEN%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! Authtoken berhasil diinstall!
    echo ========================================
    echo.
    echo Sekarang jalankan: ngrok.exe http 8000
    echo.
) else (
    echo.
    echo ========================================
    echo ERROR: Gagal install authtoken
    echo ========================================
    echo.
    echo Pastikan:
    echo 1. Authtoken sudah benar (copy lengkap)
    echo 2. Tidak ada spasi di awal/akhir
    echo.
)

pause








