# Setup Tunnel untuk Localhost (Gratis)

Untuk mengirim PDF via WhatsApp dari localhost, Anda perlu tunnel publik karena Fonte API tidak bisa akses `localhost` atau `127.0.0.1`.

## Opsi 1: LocalTunnel (GRATIS, Recommended)

### Install LocalTunnel
```bash
npm install -g localtunnel
```

### Jalankan Tunnel
```bash
lt --port 8000
```

### Copy URL yang muncul
Contoh: `https://random-name-1234.loca.lt`

### Update .env
```env
TUNNEL_URL=https://random-name-1234.loca.lt
# atau
NGROK_URL=https://random-name-1234.loca.lt
```

### Catatan
- **GRATIS** - Tidak perlu bayar
- URL berubah setiap kali restart (kecuali pakai subdomain custom)
- Jangan tutup terminal saat tunnel aktif

## Opsi 2: Cloudflare Tunnel (GRATIS)

### Install Cloudflared
Download dari: https://github.com/cloudflare/cloudflared/releases

### Jalankan Tunnel
```bash
cloudflared tunnel --url http://localhost:8000
```

### Copy URL yang muncul
Contoh: `https://random-name.trycloudflare.com`

### Update .env
```env
TUNNEL_URL=https://random-name.trycloudflare.com
```

## Opsi 3: Serveo (GRATIS, via SSH)

### Jalankan (jika punya SSH)
```bash
ssh -R 80:localhost:8000 serveo.net
```

## Opsi 4: Deploy ke Server (Production)

Jika sudah deploy ke server dengan domain publik, tidak perlu tunnel:
```env
APP_URL=https://yourdomain.com
TUNNEL_URL=  # Kosongkan
```

## Testing

Setelah setup tunnel, test URL PDF:
1. Generate PDF dari sistem
2. Buka URL: `{TUNNEL_URL}/storage/pdfs/diagnosis-{id}.pdf`
3. Jika bisa diakses di browser, Fonte API juga bisa akses

## Troubleshooting

### URL tidak bisa diakses
- Pastikan tunnel masih running
- Pastikan Laravel server running di port 8000
- Pastikan storage link sudah dibuat: `php artisan storage:link`

### Fonte API masih tidak bisa kirim PDF
- Pastikan URL benar-benar bisa diakses publik (test di browser)
- Pastikan file PDF tidak terlalu besar (max 16MB untuk WhatsApp)
- Cek log Laravel untuk error detail


