# Cara Mendapatkan API Key dari Fonte

## 🎯 Langkah-Langkah Detail

### Step 1: Login ke Dashboard Fonte

1. Buka browser dan kunjungi: **https://fonnte.com**
2. Login dengan akun Anda
3. Setelah login, Anda akan masuk ke **Dashboard**

### Step 2: Dapatkan API Key

Berdasarkan dokumentasi Fonte, API Key bisa didapatkan dengan cara:

#### **Cara 1: Setelah Add Device (Paling Mudah)**

1. **Tambahkan Device dulu** (jika belum):
   - Klik menu **"Device"** di sidebar
   - Klik tombol **"Add Device"** atau **"Tambah Device"**
   - Isi form:
     - Device Name: (nama device Anda)
     - Device Number: (nomor WhatsApp Anda, contoh: 8137685765)
     - Chatbot: Off (untuk sekarang)
     - Personal: Off
     - Group: Off
   - Klik **"Save"** atau **"Add"**

2. **Setelah device dibuat:**
   - Kembali ke halaman **"Device"**
   - Di list device, Anda akan melihat device yang baru dibuat
   - Di kolom device tersebut, ada **"API Key"** atau **"Token"**
   - Klik API Key tersebut untuk copy

#### **Cara 2: Melalui Menu "Profile"**

1. Klik menu **"Profile"** di sidebar
2. Di halaman Profile, scroll ke bawah
3. Cari section **"Token"** atau **"API Key"** atau **"Account Token"**
4. API Key akan ditampilkan di sana
5. Klik tombol **"Copy"** atau **"Show"** untuk melihat API Key lengkap

#### **Cara 3: Melalui Documentation**

1. Klik menu **"Documentation"** (di sidebar)
2. Di dokumentasi, klik section **"API"** → **"Token (API key)"**
3. Di halaman tersebut akan ada penjelasan lengkap cara mendapatkan API Key
4. Biasanya ada link langsung ke halaman Profile atau Device

**Catatan:** Berdasarkan dokumentasi Fonte, API Key biasanya ada di:
- Menu **"Profile"** → Section **"Token"** atau **"API Key"**
- Atau di menu **"Setting"** → Tab **"API"**

### Step 3: Generate API Key (Jika Belum Ada)

Jika Anda belum punya API Key:

1. Di halaman Settings/API, cari tombol:
   - **"Generate API Key"**
   - **"Create API Key"**
   - **"New API Key"**
   - **"Generate Token"**

2. Klik tombol tersebut
3. API Key akan dibuat dan ditampilkan
4. **PENTING:** Copy API Key segera karena biasanya hanya ditampilkan sekali

### Step 4: Copy API Key

1. Pilih seluruh teks API Key
2. Copy (Ctrl+C atau klik tombol Copy)
3. Simpan di tempat aman (temporary)

### Step 5: Paste ke File .env

1. Buka file: `s_pakar_backend/.env`
2. Cari atau tambahkan baris:
   ```env
   FONTE_API_KEY=
   ```
3. Paste API Key setelah tanda `=`
4. Jangan ada spasi sebelum/sesudah `=`

**Contoh:**
```env
FONTE_API_KEY=abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
FONTE_BASE_URL=https://api.fonnte.com
```

### Step 6: Clear Config Cache

```bash
cd s_pakar_backend
php artisan config:clear
php artisan cache:clear
```

## 🔍 Jika Tidak Menemukan API Key

### Checklist:

1. ✅ Apakah Anda sudah login?
2. ✅ Apakah akun Anda sudah terverifikasi?
3. ✅ Apakah Anda sudah connect device WhatsApp? (Menu "Device")
4. ✅ Apakah plan Anda sudah aktif?

### Alternatif:

1. **Cek Email:** Fonte mungkin mengirim API Key via email saat registrasi
2. **Hubungi Support:** Klik menu "Support" di dashboard untuk bantuan
3. **Cek Dokumentasi:** Di menu "Documentation", cari section "API" atau "Integration"

## 📝 Format API Key Fonte

API Key Fonte biasanya:
- Panjang: 30-100 karakter
- Format: Alphanumeric (huruf dan angka)
- Contoh: `FONTE_abc123def456ghi789jkl012mno345pqr678`

## ⚠️ Keamanan API Key

1. **JANGAN** share API Key ke publik
2. **JANGAN** commit API Key ke Git (pastikan `.env` ada di `.gitignore`)
3. **JANGAN** hardcode API Key di code
4. **JANGAN** screenshot API Key dan share
5. Jika ter-expose, segera **regenerate** di dashboard Fonte

## ✅ Verifikasi API Key

Setelah setup, test dengan:

```bash
cd s_pakar_backend
php artisan tinker
```

```php
$whatsapp = app(\App\Services\WhatsAppService::class);
$result = $whatsapp->sendOtp('6281234567890', '123456');
dd($result);
```

Jika berhasil, akan return `['success' => true, ...]`

## 🆘 Masih Bingung?

1. **Cek Tutorial Fonte:** Menu "Tutorial" → "How to send message with API?"
2. **Hubungi Support:** Menu "Support" di dashboard
3. **Cek YouTube:** Menu "Youtube" untuk video tutorial

