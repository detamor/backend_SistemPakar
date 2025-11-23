# Fix Fonte Base URL Error

## ❌ Error yang Terjadi
```
cURL error 6: Could not resolve host: api.fonte.com
```

## ✅ Solusi

### 1. Edit File `.env`

Buka file: `s_pakar_backend/.env`

**Pastikan ada baris ini:**
```env
FONTE_API_KEY=b35r3UaG4XqF4wWRhBbc
FONTE_BASE_URL=https://api.fonnte.com
```

**PENTING:** 
- `api.fonnte.com` (dengan **double 'n'** - fonnte)
- Bukan `api.fonte.com` (salah - hanya satu 'n')

### 2. Clear Config Cache

Jalankan di terminal:
```bash
cd s_pakar_backend
php artisan config:clear
php artisan cache:clear
```

### 3. Restart Laravel Server

Jika menggunakan `php artisan serve`, restart server:
- Stop dengan Ctrl+C
- Start lagi: `php artisan serve`

Jika menggunakan Laragon/Apache, restart service.

### 4. Verifikasi

Test lagi register dengan nomor: `+62 896-0201-5724`

## 🔍 Cek Base URL di Code

Pastikan di `app/Services/WhatsAppService.php`:
```php
$this->baseUrl = env('FONTE_BASE_URL', 'https://api.fonnte.com');
```

Default value sudah benar (`api.fonnte.com`), jadi jika `.env` tidak ada, akan pakai default yang benar.

## ⚠️ Jika Masih Error

1. Cek file `.env` benar-benar ada `FONTE_BASE_URL=https://api.fonnte.com`
2. Pastikan tidak ada typo
3. Restart Laravel server
4. Cek log: `storage/logs/laravel.log`

