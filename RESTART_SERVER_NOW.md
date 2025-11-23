# ⚠️ RESTART LARAVEL SERVER - PENTING!

## Masalah
Error masih menunjukkan `api.fonte.com` (salah) padahal `.env` sudah benar `api.fonnte.com`.

## ✅ Solusi: RESTART SERVER

### Jika menggunakan Laragon:

1. **Buka Laragon**
2. **Stop semua service:**
   - Klik "Stop All"
3. **Start lagi:**
   - Klik "Start All"
4. **Atau restart Apache/MySQL saja:**
   - Klik kanan pada Apache → Restart

### Jika menggunakan `php artisan serve`:

1. **Stop server:**
   - Di terminal yang menjalankan server, tekan **Ctrl+C**
2. **Start lagi:**
   ```bash
   cd s_pakar_backend
   php artisan serve
   ```

### Setelah Restart:

1. **Clear cache lagi (untuk memastikan):**
   ```bash
   cd s_pakar_backend
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Test lagi register dengan nomor:**
   - `+62 896-0201-5724`
   - `089602015724`

## 🔍 Verifikasi

Setelah restart, cek log:
```bash
tail -f storage/logs/laravel.log
```

Atau test langsung di browser dengan register form.

## ✅ Checklist

- [ ] `.env` sudah benar: `FONTE_BASE_URL=https://api.fonnte.com`
- [ ] Server sudah di-restart
- [ ] Cache sudah di-clear
- [ ] Test register lagi

