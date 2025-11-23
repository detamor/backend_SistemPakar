# Setup API Key Fonte - Langkah Cepat

## ✅ API Key Anda
```
b35r3UaG4XqF4wWRhBbc
```

## 📝 Langkah Setup

### 1. Edit File `.env`

Buka file: `s_pakar_backend/.env`

Tambahkan atau edit baris berikut:

```env
FONTE_API_KEY=b35r3UaG4XqF4wWRhBbc
FONTE_BASE_URL=https://api.fonnte.com
```

**PENTING:** 
- Jangan ada spasi sebelum/sesudah `=`
- Jangan ada tanda kutip
- Pastikan tidak ada karakter tersembunyi

### 2. Clear Config Cache

Jalankan di terminal:

```bash
cd s_pakar_backend
php artisan config:clear
php artisan cache:clear
```

### 3. Test Koneksi

Jalankan di terminal:

```bash
php artisan tinker
```

Kemudian ketik:

```php
$whatsapp = app(\App\Services\WhatsAppService::class);
$result = $whatsapp->sendOtp('628137685765', '123456');
dd($result);
```

**Catatan:** Ganti `628137685765` dengan nomor WhatsApp yang valid (format: 62 + nomor tanpa 0)

### 4. Verifikasi

Jika berhasil, akan return:
```php
[
  "success" => true,
  "data" => [...],
  "message" => "Pesan berhasil dikirim"
]
```

Jika gagal, cek:
- Log Laravel: `storage/logs/laravel.log`
- Response error di `$result`

## 🔧 Troubleshooting

### Error: "Fonte API Key tidak dikonfigurasi"
- Pastikan `.env` sudah di-update
- Pastikan sudah clear config cache
- Cek apakah ada typo di nama variable

### Error: "401 Unauthorized"
- Cek API key sudah benar (tidak ada spasi)
- Pastikan device sudah aktif di dashboard Fonte
- Cek apakah token masih valid

### Error: "Invalid target"
- Pastikan format nomor: `628137685765` (tanpa +, spasi, dash)
- Nomor harus valid WhatsApp number

## ✅ Checklist

- [ ] API Key sudah di-paste ke `.env`
- [ ] `FONTE_BASE_URL` sudah di-set
- [ ] Config cache sudah di-clear
- [ ] Test koneksi berhasil
- [ ] OTP bisa terkirim via WhatsApp

## 🎯 Next Step

Setelah API key berhasil, sistem register dan reset password akan otomatis menggunakan Fonte untuk kirim OTP!

