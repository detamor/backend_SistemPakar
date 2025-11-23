# Cara Connect Device WhatsApp di Fonte

## 🎯 Langkah-Langkah Detail

### Step 1: Login ke Dashboard Fonte

1. Buka browser: **https://fonnte.com**
2. Login dengan akun Anda

### Step 2: Akses Menu "Device"

1. Di sidebar kiri, klik menu **"Device"**
2. Anda akan melihat daftar device (jika ada)

### Step 3: Add Device (Jika Belum Ada)

1. Klik tombol **"Add Device"** atau **"Tambah Device"**
2. Isi form:
   - **Device Name**: Nama device (contoh: "Device Utama" atau "WhatsApp Bot")
   - **Device Number**: Nomor WhatsApp yang akan digunakan
     - Contoh: `8137685765` (tanpa 0 di depan, tanpa 62)
     - Atau: `89602015724` (nomor yang akan digunakan untuk kirim OTP)
   - **Chatbot**: Off (untuk sekarang)
   - **Personal**: Off
   - **Group**: Off
3. Klik **"Save"** atau **"Add"**

### Step 4: Scan QR Code

Setelah device dibuat, akan muncul **QR Code**:

1. **Buka WhatsApp di HP Anda**
2. Pergi ke:
   - **Settings** (Pengaturan)
   - **Linked Devices** (Perangkat Tertaut)
   - **Link a Device** (Tautkan Perangkat)
3. **Scan QR Code** yang muncul di dashboard Fonte
4. **Tunggu** sampai muncul konfirmasi "Device Linked"

### Step 5: Verifikasi Status

1. Kembali ke dashboard Fonte
2. Di menu "Device", cek status device:
   - ✅ **"Connected"** (hijau) = Device sudah terhubung
   - ❌ **"Disconnected"** (merah) = Device belum terhubung

### Step 6: Test Kirim Pesan

Setelah status "Connected":
1. Coba register lagi di aplikasi
2. OTP seharusnya sudah bisa terkirim ke WhatsApp

## ⚠️ Troubleshooting

### QR Code Tidak Muncul
- Refresh halaman dashboard Fonte
- Coba hapus device dan buat lagi

### QR Code Expired
- QR Code biasanya berlaku 1-2 menit
- Klik "Refresh QR" atau buat device baru

### Status Tetap "Disconnected"
- Pastikan WhatsApp di HP terhubung ke internet
- Pastikan sudah scan QR Code dengan benar
- Coba scan ulang QR Code
- Restart WhatsApp di HP

### Device Sering Disconnect
- Pastikan HP tetap terhubung ke internet
- Jangan logout WhatsApp di HP
- Jangan unlink device dari WhatsApp

## 📱 Catatan Penting

1. **Device harus selalu connected** untuk bisa kirim pesan
2. **Satu device** bisa digunakan untuk kirim ke banyak nomor
3. **Nomor device** (yang di-add) tidak harus sama dengan nomor yang akan dikirimi OTP
4. Device bisa digunakan untuk kirim ke nomor lain (tidak harus nomor device sendiri)

## ✅ Checklist

- [ ] Device sudah dibuat di dashboard Fonte
- [ ] QR Code sudah di-scan dengan WhatsApp
- [ ] Status device "Connected" (hijau)
- [ ] Test register lagi dengan nomor: `+62 896-0201-5724`
- [ ] OTP terkirim ke WhatsApp

## 🎯 Setelah Device Connected

Setelah device connected, sistem akan otomatis bisa kirim OTP via WhatsApp untuk:
- ✅ Register user baru
- ✅ Reset password
- ✅ Notifikasi lainnya

