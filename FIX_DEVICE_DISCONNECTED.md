# Fix: Device Disconnected Error

## ❌ Error yang Terjadi

Response dari Fonte API:
```json
{
  "reason": "request invalid on disconnected device",
  "status": false
}
```

## ✅ Solusi

### Device WhatsApp Belum Terhubung!

Anda perlu menghubungkan device WhatsApp di dashboard Fonte terlebih dahulu.

### Langkah-langkah:

1. **Login ke Dashboard Fonte**
   - Buka: https://fonnte.com
   - Login dengan akun Anda

2. **Akses Menu "Device"**
   - Klik menu **"Device"** di sidebar
   - Anda akan melihat daftar device

3. **Connect Device WhatsApp**
   - Jika belum ada device, klik **"Add Device"**
   - Isi form:
     - Device Name: (nama device Anda)
     - Device Number: (nomor WhatsApp yang akan digunakan, contoh: 8137685765)
   - Klik **"Save"**

4. **Scan QR Code**
   - Setelah device dibuat, akan muncul **QR Code**
   - Buka WhatsApp di HP Anda
   - Pergi ke **Settings** → **Linked Devices** → **Link a Device**
   - Scan QR Code yang muncul di dashboard Fonte

5. **Tunggu Status "Connected"**
   - Setelah scan, tunggu beberapa detik
   - Status device akan berubah menjadi **"Connected"** (hijau)
   - Jika masih "Disconnected", coba scan ulang

6. **Test Lagi**
   - Setelah device connected, coba register lagi
   - OTP seharusnya sudah bisa terkirim

## 🔍 Verifikasi Device Status

Di dashboard Fonte → Menu "Device":
- ✅ Status harus **"Connected"** (hijau)
- ❌ Jika **"Disconnected"** (merah), device belum terhubung

## ⚠️ Catatan Penting

1. **Device harus selalu connected** untuk bisa kirim pesan
2. Jika device disconnect, semua request akan gagal
3. Pastikan WhatsApp di HP tetap aktif dan terhubung ke internet
4. Jika device sering disconnect, cek koneksi internet

## ✅ Checklist

- [ ] Device sudah dibuat di dashboard Fonte
- [ ] QR Code sudah di-scan dengan WhatsApp
- [ ] Status device "Connected" (hijau)
- [ ] Test register lagi dengan nomor: `+62 896-0201-5724`

Setelah device connected, OTP akan otomatis terkirim!

