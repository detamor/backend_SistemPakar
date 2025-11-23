# Setup Fonte API untuk WhatsApp

## 📋 Langkah Setup

### 1. Daftar/Login ke Fonte

1. Buka website Fonte: https://fonnte.com
2. Daftar akun baru atau login jika sudah punya akun
3. Setelah login, Anda akan masuk ke dashboard

### 2. Cara Mendapatkan API Key

Berdasarkan dashboard Fonte yang Anda tunjukkan, ikuti langkah berikut:

#### Opsi 1: Melalui Menu "Setting"
1. Klik menu **"Setting"** di sidebar kiri (di bagian bawah, setelah "Support")
2. Di halaman Setting, cari bagian **"API"** atau **"API Key"**
3. Copy API Key yang ditampilkan
4. Jika belum ada, klik tombol **"Generate API Key"** atau **"Create API Key"**

#### Opsi 2: Melalui Menu "Documentation"
1. Klik menu **"Documentation"** (di bagian Info)
2. Di dokumentasi, biasanya ada contoh penggunaan API dengan API Key
3. Atau ada link langsung ke halaman API Key

#### Opsi 3: Langsung ke URL
Coba akses langsung:
- `https://fonnte.com/setting` atau
- `https://fonnte.com/api` atau
- `https://fonnte.com/settings/api`

### 3. Format API Key Fonte

API Key Fonte biasanya berbentuk:
- String panjang (contoh: `abc123def456ghi789...`)
- Atau token dengan format tertentu

**PENTING:** 
- Jangan share API Key ke publik
- Simpan dengan aman
- Jika ter-expose, segera regenerate di dashboard Fonte

### 3. Konfigurasi di Laravel

#### Edit file `.env` di `s_pakar_backend`:

```env
# Fonte WhatsApp API Configuration
FONTE_API_KEY=paste_api_key_dari_fonte_di_sini
FONTE_BASE_URL=https://api.fonnte.com
```

**Langkah:**
1. Buka file `s_pakar_backend/.env`
2. Cari atau tambahkan baris `FONTE_API_KEY=`
3. Paste API Key yang sudah di-copy dari dashboard Fonte
4. Pastikan tidak ada spasi sebelum/sesudah `=`

**Contoh:**
```env
FONTE_API_KEY=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
FONTE_BASE_URL=https://api.fonnte.com
```

**Catatan:** 
- `FONTE_BASE_URL` biasanya `https://api.fonnte.com` (cek di dokumentasi Fonte)
- Jika berbeda, sesuaikan dengan base URL yang ada di dokumentasi

### 3. Format Nomor Telepon

Fonte API biasanya membutuhkan format nomor:
- **Format:** `6281234567890` (tanpa +, tanpa spasi, tanpa dash)
- **Contoh:** 
  - Input: `081234567890` → Format: `6281234567890`
  - Input: `+6281234567890` → Format: `6281234567890`
  - Input: `0812-3456-7890` → Format: `6281234567890`

### 4. Test Koneksi

#### Test via Tinker:

```bash
cd s_pakar_backend
php artisan tinker
```

```php
$whatsapp = app(\App\Services\WhatsAppService::class);
$result = $whatsapp->sendMessage('6281234567890', 'Test pesan dari Laravel');
dd($result);
```

#### Test via API Endpoint:

```bash
# Pastikan sudah login dan dapat token
curl -X POST http://localhost:8000/api/whatsapp/send \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "6281234567890",
    "message": "Test pesan"
  }'
```

## 🔧 Troubleshooting

### Error: "Fonte API Key tidak dikonfigurasi"

**Solusi:**
1. Pastikan `.env` sudah di-update dengan `FONTE_API_KEY`
2. Clear config cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Error: "401 Unauthorized"

**Solusi:**
1. Cek API Key sudah benar di `.env`
2. Pastikan API Key masih aktif di dashboard Fonte
3. Cek format Authorization header (harus `Bearer {api_key}`)

### Error: "Invalid phone number"

**Solusi:**
1. Pastikan format nomor: `6281234567890` (tanpa +, spasi, dash)
2. Nomor harus valid WhatsApp number
3. Cek apakah nomor sudah terdaftar di WhatsApp

### Error: "Rate limit exceeded"

**Solusi:**
1. Cek limit API di dashboard Fonte
2. Tambahkan delay antar request jika perlu
3. Upgrade plan jika limit terlalu kecil

## 📝 Format Endpoint Fonte API

Berdasarkan implementasi saat ini, endpoint yang digunakan:

```
POST {FONTE_BASE_URL}/messages
Headers:
  Authorization: Bearer {FONTE_API_KEY}
  Content-Type: application/json
Body:
{
  "to": "6281234567890",
  "message": "Pesan Anda",
  "type": "text"
}
```

**Catatan:** Format ini bisa berbeda tergantung dokumentasi Fonte API yang sebenarnya. 
Jika format berbeda, edit file `app/Services/WhatsAppService.php`

## 🔄 Alternatif Format API

Jika Fonte API menggunakan format berbeda, edit method `sendMessage()` di `WhatsAppService.php`:

### Opsi 1: Format dengan `phone` bukan `to`
```php
->post($this->baseUrl . '/messages', [
    'phone' => $phoneNumber,
    'text' => $message,
])
```

### Opsi 2: Format dengan `recipient` dan `body`
```php
->post($this->baseUrl . '/send', [
    'recipient' => $phoneNumber,
    'body' => $message,
])
```

### Opsi 3: Format dengan query parameter
```php
->post($this->baseUrl . '/send?to=' . $phoneNumber, [
    'message' => $message,
])
```

## ✅ Checklist

- [ ] API Key sudah didapat dari Fonte
- [ ] `.env` sudah di-update dengan `FONTE_API_KEY` dan `FONTE_BASE_URL`
- [ ] Config cache sudah di-clear
- [ ] Test koneksi berhasil
- [ ] Format nomor sudah benar (6281234567890)
- [ ] OTP bisa terkirim via WhatsApp

## 📞 Support

Jika masih ada masalah:
1. Cek log Laravel: `storage/logs/laravel.log`
2. Cek dokumentasi resmi Fonte API
3. Test langsung via Postman/curl dengan API Key

