
# Endpoint Wajib Webta (Mapping UI -> API)

Dokumen ini khusus untuk kebutuhan **Webta** (frontend web). Tujuan: daftar endpoint minimum + fitur yang masih kurang agar semua halaman Webta bisa benar-benar terhubung ke backend.

Catatan umum:
- Semua endpoint di bawah diasumsikan di prefix `/api`.
- Gunakan auth berbasis token (Sanctum/Bearer). Simpan token setelah login.
- Status UI Webta: `hadir, izin, sakit, alpha, pulang` → perlu mapping ke status backend (lihat bagian **Mapping Status**).

---

## 1) Auth & Session
- `POST /auth/login` → login semua role (admin, guru, waka, wali-kelas, siswa, pengurus-kelas)
- `POST /auth/logout` → logout
- `GET /me` → profil + role aktif

## 2) Admin (Data Master)
### Dashboard Admin (`/admin/dashboard`)
- `GET /admin/summary` (statistik total murid/guru/kelas/jurusan)
- `GET /attendance/summary?from=&to=` (ringkasan hadir/izin/sakit/alpha/terlambat)

### Data Siswa (`/admin/siswa`)
- `GET /students?search=&class_id=&major_id=&page=&per_page=`
- `POST /students`
- `PUT /students/{id}`
- `DELETE /students/{id}`
- `POST /students/import`

### Data Guru (`/admin/guru`)
- `GET /teachers?search=&page=&per_page=`
- `POST /teachers`
- `PUT /teachers/{id}`
- `DELETE /teachers/{id}`
- `POST /teachers/import`

### Data Kelas (`/admin/kelas`)
- `GET /classes?search=&grade=&major_id=&page=&per_page=`
- `POST /classes`
- `PUT /classes/{id}`
- `DELETE /classes/{id}`

### Data Jurusan (`/admin/jurusan`)
- `GET /majors`
- `POST /majors`
- `PUT /majors/{id}`
- `DELETE /majors/{id}`

## 3) Guru
### Dashboard Guru (`/guru/dashboard`)
- `GET /me/schedules?date=YYYY-MM-DD`
- `POST /attendance/scan` (verifikasi QR dari pengurus kelas)

### Jadwal Guru (`/guru/jadwal`)
- `GET /teachers/{id}/schedule-image`

### Presensi Siswa (`/guru/presensi`)
- `GET /attendance/schedules/{schedule_id}`
- `PATCH /attendance/{attendance_id}` (ubah status hadir/izin/sakit/alpha/pulang)
- `POST /attendance/{attendance_id}/document` (upload bukti izin/sakit)
- `GET /attendance/{attendance_id}/document` (preview/download bukti)

## 4) Siswa
### Dashboard Siswa (`/siswa/dashboard`)
- `GET /me/attendance/summary?from=&to=`
- `GET /me/schedules?date=YYYY-MM-DD`

### Riwayat (`/siswa/riwayat`)
- `GET /me/attendance?from=&to=&status=`

### Jadwal (gambar, jika dipakai)
- `GET /classes/{class_id}/schedule-image`

## 5) Pengurus Kelas
### Dashboard (`/pengurus-kelas/dashboard`)
- `GET /me/class`
- `GET /me/class/schedules?date=YYYY-MM-DD`

### Presensi QR (`/pengurus-kelas/presensi`)
- `POST /me/class/qr-token` (generate QR per jadwal, ada expiry)
- `POST /qrcodes/{token}/revoke` (opsional)

### Riwayat Kelas (`/pengurus-kelas/riwayat`)
- `GET /me/class/attendance?from=&to=&status=`

## 6) Wali Kelas
### Dashboard (`/walikelas/dashboard`)
- `GET /me/homeroom`
- `GET /me/homeroom/schedules?date=YYYY-MM-DD`
- `GET /me/homeroom/attendance/summary?from=&to=`

### Data Siswa (`/walikelas/datasiswa`)
- `GET /me/homeroom/students`

### Riwayat Kehadiran (`/walikelas/riwayatkehadiran`)
- `GET /me/homeroom/attendance?from=&to=&status=`
- `PATCH /attendance/{attendance_id}` (edit status)

### Presensi (`/walikelas/presensi`)
- `GET /classes/{class_id}/attendance?date=YYYY-MM-DD`

### Jadwal Wali Kelas (`/walikelas/jadwalwakel`)
- `GET /classes/{class_id}/schedule-image`

## 7) Waka
### Dashboard (`/waka/dashboard`)
- `GET /waka/attendance/summary?from=&to=` (statistik ringkas + trend jika tersedia)

### Jadwal Guru (`/waka/jadwal-guru`)
- `GET /teachers?search=`
- `GET /teachers/{id}/schedules?from=&to=`
- `PUT /teachers/{id}` (update data guru dari form edit)
- `POST /teachers/{id}/schedule-image`
- `DELETE /teachers/{id}/schedule-image`

### Jadwal Siswa (`/waka/jadwal-siswa`)
- `GET /classes?search=&major_id=&grade=`
- `GET /classes/{id}/schedules?from=&to=`
- `PUT /classes/{id}` (update data kelas dari form edit)
- `POST /classes/{id}/schedule-image`
- `DELETE /classes/{id}/schedule-image`

### Kehadiran Siswa (`/waka/kehadiran-siswa`)
- `GET /classes?with=major,homeroom`
- `GET /classes/{id}/attendance?date=YYYY-MM-DD`

### Rekap Kehadiran Siswa (`/waka/kehadiran-siswa/rekap`)
- `GET /classes/{id}/students/attendance-summary?from=&to=`

### Kehadiran Guru (`/waka/kehadiran-guru`)
- `GET /attendance/teachers/daily?date=YYYY-MM-DD`

### Detail Kehadiran Guru (`/waka/kehadiran-guru/:id`)
- `GET /teachers/{id}/attendance?from=&to=`
- `PATCH /attendance/{attendance_id}` (edit status)

---

## Mapping Status (UI -> Backend)
- `hadir` → `present`
- `izin` → `izin` / `excused`
- `sakit` → `sick`
- `alpha` → `absent`
- `pulang` → (butuh status backend baru) **atau** map ke `excused` dengan alasan `pulang`

---

## Fitur yang Masih Kurang (Webta)
1) Integrasi API (hapus dummy/localStorage di hampir semua halaman).
2) Upload & preview bukti izin/sakit di presensi.
3) Upload & manajemen gambar jadwal (guru/kelas).
4) Trend statistik (harian/bulanan) untuk dashboard Waka/Siswa.
5) Role-based guard (redirect jika token/role salah).
6) QR flow lengkap: generate token, expiry, anti-reuse, scan/verify di guru.

---

## Catatan QR
- `html5-qrcode` cocok untuk scan di browser (guru/wali).
- `qrcode-terminal` hanya untuk CLI (server/console), bukan untuk UI web.
- Untuk menampilkan QR di Webta, gunakan endpoint QR token + library QR di frontend.
