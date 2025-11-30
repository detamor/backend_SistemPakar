@echo off
echo ========================================
echo LocalTunnel - Tunnel untuk Port 8000
echo ========================================
echo.
echo Menjalankan LocalTunnel...
echo.
echo PENTING:
echo 1. Copy URL yang muncul (contoh: https://random-name-1234.loca.lt)
echo 2. Update NGROK_URL di .env dengan URL tersebut
echo 3. Jangan tutup window ini!
echo.
echo ========================================
echo.

lt --port 8000

pause






