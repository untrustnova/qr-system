# TODO Web/Desktop vs Backend (Status Fitur)

Dokumen ini merangkum fitur Web/Desktop yang direncanakan vs yang sudah tersedia di backend saat ini.

## Siswa
**Sudah ada di backend**
- Melihat jadwal (bisa via `GET /schedules` dan `GET /me/schedules`).
- Melihat daftar ketidakhadiran pribadi (via `GET /me/attendance`).

**Belum ada / belum lengkap**
- Daftar ketidakhadiran per siswa (global) — hanya admin/guru bisa lihat data lain.

## Admin
**Sudah ada di backend**
- CRUD konsentrasi keahlian (majors).
- CRUD data kelas (classes).
- CRUD data guru (teachers) + import.
- CRUD data siswa (students) + import.
- Detail siswa & guru (GET show).

**Belum ada / belum lengkap**
- Tidak ada catatan khusus untuk fitur admin di daftar ini.

## Guru
**Sudah ada di backend**
- Melihat jadwal (GET /schedules dengan role teacher).
- Presensi via QR (POST /attendance/scan).
- Lihat presensi siswa per jadwal (GET /attendance/schedules/{schedule}).
- Edit status presensi siswa (POST /attendance/{attendance}/excuse).
- Flow "scan QR dari pengurus kelas" (Guru bisa scan QR tipe 'student').

**Belum ada / belum lengkap**


## Waka (Admin type: waka)
**Sudah ada di backend**
- CRUD jadwal kelas (via schedules CRUD + bulk schedule per kelas per hari).
- Rekap kehadiran siswa (GET /attendance/recap, summary per kelas & per jadwal).
- Daftar ketidakhadiran per siswa (belum ada endpoint khusus, hanya bisa via export/rekap global).
- Approve/reject perizinan (absence requests).

**Belum ada / belum lengkap**
- CRUD jadwal guru secara spesifik (backend hanya satu tabel schedules; belum ada endpoint khusus “jadwal guru”).
- CRUD kehadiran guru & siswa (belum ada endpoint create/update attendance manual selain `markExcuse` dan `void`).
- Kehadiran guru per hari (belum ada endpoint filter khusus guru per tanggal).
- Daftar ketidakhadiran per siswa (per siswa) belum ada endpoint ringkas, hanya export.

## Wali Kelas (Wakel)
**Sudah ada di backend**
- Melihat jadwal (GET /schedules untuk teacher).
- Presensi via QR (POST /attendance/scan).
- Edit & lihat kehadiran siswa per jadwal (GET /attendance/schedules/{schedule}`, `POST /attendance/{attendance}/excuse`).
- Input perizinan (absence request) untuk siswa kelasnya (dengan batasan homeroom/teaching).
- Rekap kelas (summary per kelas tersedia).

**Belum ada / belum lengkap**
- Kehadiran siswa kelasnya (list per tanggal/per sesi) — belum ada endpoint khusus untuk per kelas/per tanggal.
- Rekap siswa kelasnya (ringkasan per siswa untuk kelas) — belum ada endpoint agregasi.
- Daftar ketidakhadiran per siswa khusus kelasnya — belum ada endpoint ringkas.

## Catatan Teknis
- Endpoint jadwal (`/schedules`) saat ini tidak mengizinkan role siswa.
- Status presensi: validasi `markExcuse` menerima `dinas/izin` tapi enum DB hanya `present, late, excused, sick, absent`.

