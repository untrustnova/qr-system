# QR System (WhatEkster) - Dokumentasi

Dokumentasi ini menjelaskan cara menjalankan layanan, konfigurasi, dan API yang tersedia untuk sistem WhatsApp berbasis QR menggunakan Baileys.

## Ringkasan
- Server: Express di `server.js`.
- WhatsApp client: Baileys, inisialisasi di `client/wa-client.js`.
- Auth state: Multi-file auth di folder `./auth` (default) atau lokasi lain via flag `--auth`.
- API route: `/api` di `router/route.js`.

## Struktur File Utama
- `server.js`: Bootstrap Express, middleware, dan route API.
- `client/wa-middleware.js`: Inisialisasi WhatsApp client + inject ke request.
- `client/wa-client.js`: Koneksi Baileys, QR generation, status koneksi.
- `router/route.js`: Endpoint `/api/status` dan `/api/send-message`.
- `lib/base-to-buf.js`: Konversi Base64 (data URI atau string) ke Buffer.
- `entrypoint.sh`: Entry point container, menjalankan server dengan `--auth /app/auth`.

## Cara Menjalankan

### 1) Lokal (Node.js)
1. Install dependency:
```
npm install
```
2. Jalankan server:
```
node server.js
```
Opsional, set lokasi auth:
```
node server.js --auth ./auth
```

Server default berjalan di port `3050` (bisa diubah lewat env `PORT`).

### 2) Docker
Build dan run:
```
docker build -t whatekster .
docker run -p 3050:3050 -v $(pwd)/auth:/app/auth whatekster
```
Catatan:
- Container menjalankan `entrypoint.sh` yang selalu memakai `--auth /app/auth`, jadi volume `./auth:/app/auth` disarankan agar sesi login tersimpan di host.
- Jika ingin ganti port, gunakan env `PORT` dan mapping port yang sama, contoh:
```
docker run -p 4000:4000 -e PORT=4000 -v $(pwd)/auth:/app/auth whatekster
```

### 3) Docker Compose
```
docker compose up --build
```

## Konfigurasi

### Environment Variables
- `PORT`: Port server (default `3050`).
- `ENABLE_QRCODE_CLI`: Jika diset, QR akan ditampilkan di terminal (qrcode-terminal).

### Argumen CLI
- `--auth <path>`: Lokasi folder auth state. Jika tidak ada, default `./auth`. Jika folder belum ada, akan dibuat.

## Alur QR dan Koneksi
1. Server start, Baileys akan connect.
2. Jika perlu login, QR akan dibuat:
   - Disimpan di memory `req.whatsapp._info.qr` (string).
   - Versi image data URL ada di `req.whatsapp._info.qrImg`.
3. Setelah scan sukses, status `ready` menjadi `true`.

## API
Base path: `http://localhost:3050/api`

### GET `/status`
Mengembalikan status koneksi dan QR.

Query:
- `qrcode_img=1` (atau nilai apa pun) untuk response berupa image PNG dari QR.

#### Response JSON (default)
```
{
  "data": {
    "ready": true,
    "problem": null,
    "qrcode": "...",
    "qrcode_img": "data:image/png;base64,..."
  }
}
```

#### Response jika `qrcode_img` ada
- Content-Type: `image/png`
- Body: binary PNG (hasil decode data URI QR).

### POST `/send-message`
Mengirim pesan teks atau media ke nomor WhatsApp.

Body (JSON):
- `to`: nomor tujuan (boleh dengan simbol, akan dibersihkan jadi digit).
- `message`: string wajib.
- `media_url` (opsional): URL HTTP(S) atau Base64 data URI.
- `media_type` (opsional): salah satu dari `image`, `video`, `sticker`, `document`, `audio`.

Validasi:
- `message` wajib string.
- `media_type` jika diisi harus termasuk salah satu tipe di atas.

#### Contoh kirim teks
```
curl -X POST http://localhost:3050/api/send-message \
  -H "Content-Type: application/json" \
  -d '{"to":"628123456789","message":"Halo"}'
```

#### Contoh kirim media via URL
```
curl -X POST http://localhost:3050/api/send-message \
  -H "Content-Type: application/json" \
  -d '{
    "to":"628123456789",
    "message":"Ini gambar",
    "media_type":"image",
    "media_url":"https://example.com/image.jpg"
  }'
```

#### Contoh kirim media via Base64 data URI
```
curl -X POST http://localhost:3050/api/send-message \
  -H "Content-Type: application/json" \
  -d '{
    "to":"628123456789",
    "message":"Ini PDF",
    "media_type":"document",
    "media_url":"data:application/pdf;base64,JVBERi0xLjcKJ..."
  }'
```

#### Response sukses
```
{
  "message": "Success!",
  "data": { ... } 
}
```

#### Response error
- `400` jika `message` kosong atau `media_type` tidak valid.
- `500` untuk error internal.

## Catatan Teknis
- Nomor tujuan dibentuk ke format `@s.whatsapp.net`.
- Auth state tersimpan di folder `auth` (atau sesuai `--auth`).
- QR image disimpan sebagai data URI (base64) dan dapat diambil via `/api/status`.

## Troubleshooting Singkat
- Jika QR terus berubah, pastikan belum login di perangkat lain dan scan QR terbaru.
- Jika auth bermasalah, hapus folder `auth` lalu restart untuk re-scan QR.
