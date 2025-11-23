# Test Fonte API - Debug OTP Tidak Masuk

## 🔍 Langkah Debugging

### 1. Cek Log Laravel

Buka file: `s_pakar_backend/storage/logs/laravel.log`

Cari log dengan keyword:
- "Fonte API Request"
- "Fonte API Response"
- "Failed to send OTP"

### 2. Test Manual via Tinker

```bash
cd s_pakar_backend
php artisan tinker
```

```php
$whatsapp = app(\App\Services\WhatsAppService::class);
$result = $whatsapp->sendOtp('6289602015724', '123456');
dd($result);
```

**Catatan:** 
- Nomor harus format: `6289602015724` (tanpa +, spasi, dash)
- Dari `+62 896-0201-5724` → `6289602015724`

### 3. Cek Response dari Fonte

Setelah test, cek:
- `$result['success']` - apakah true atau false
- `$result['error']` - jika ada error, apa pesannya
- `$result['data']` - response dari Fonte API

### 4. Kemungkinan Masalah

#### A. Format API Salah
- Cek dokumentasi Fonte: mungkin format berbeda
- Mungkin perlu parameter tambahan
- Mungkin endpoint berbeda

#### B. Device Belum Terhubung
- Pastikan device WhatsApp sudah terhubung di dashboard Fonte
- Cek menu "Device" di dashboard Fonte
- Pastikan device status "Connected"

#### C. Nomor Tidak Valid
- Pastikan nomor `6289602015724` sudah terdaftar di WhatsApp
- Nomor harus aktif dan bisa menerima pesan
- Cek apakah nomor diblokir

#### D. API Key Tidak Valid
- Pastikan API Key `b35r3UaG4XqF4wWRhBbc` masih aktif
- Cek di dashboard Fonte apakah token masih valid
- Mungkin perlu regenerate token

### 5. Format API Fonte yang Benar

Berdasarkan dokumentasi Fonte, format bisa berbeda. Cek di:
- Dashboard Fonte → Documentation → API → Sending API Messages

Kemungkinan format:
```php
// Opsi 1: Format saat ini
POST https://api.fonnte.com/send
Headers: Authorization: {token}
Body: { "target": "6289602015724", "message": "..." }

// Opsi 2: Mungkin perlu phone bukan target
Body: { "phone": "6289602015724", "text": "..." }

// Opsi 3: Mungkin perlu device_id
Body: { "target": "6289602015724", "message": "...", "device_id": "..." }
```

## ✅ Next Step

1. Test via tinker dulu untuk lihat response detail
2. Cek log untuk melihat request/response
3. Jika masih error, cek dokumentasi Fonte untuk format yang benar
4. Pastikan device sudah connected di dashboard Fonte

