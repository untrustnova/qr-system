# TODO Mobile vs Backend (Status Fitur)

Dokumen ini merangkum fitur Android yang sudah ada di backend vs yang belum tersedia berdasarkan kode saat ini.

## Guru
**Sudah ada di backend**
- Daftar jadwal harian (filter `date` di `GET /schedules`).
- Scan QR untuk absensi (`POST /attendance/scan`).
- Mengajukan dispensasi/sakit/izin siswa (`POST /absence-requests`).

**Belum ada / belum lengkap**
- Statistik kehadiran mengajar (per guru) — belum ada endpoint statistik khusus guru.
- Riwayat kehadiran pribadi guru lintas jadwal — belum ada endpoint khusus.
- Tindak lanjut siswa (sering izin/alpa/sakit dari semua kelas yang diajar) — belum ada agregasi per guru.
- Izin tidak mengajar (guru) — belum ada flow/endpoint khusus untuk izin guru.
- Membuat izin/keterangan sakit (guru) — backend hanya untuk izin siswa (absence requests).
- Notifikasi harian otomatis — belum ada scheduler/push endpoint di backend.

## Wali Kelas
**Sudah ada di backend**
- Akses jadwal & scan QR (sama seperti guru).
- Rekap per jadwal dan ringkasan status per kelas (`GET /attendance/schedules/{schedule}`, `GET /attendance/classes/{class}/summary`).

**Belum ada / belum lengkap**
- Riwayat kehadiran kelas per sesi (timeline semua jadwal kelas yang dibimbing) — belum ada endpoint khusus.
- Tindak lanjut siswa khusus kelas (sering izin/alpa/sakit) — belum ada agregasi per kelas.
- Statistik dashboard khusus wali kelas — belum ada endpoint ringkasan.

## Siswa
**Sudah ada di backend**
- Riwayat presensi pribadi (`GET /me/attendance`).
- Scan QR presensi (`POST /attendance/scan`) + device binding (`POST /me/devices`).
- Pengurus kelas bisa generate/revoke QR (`POST /qrcodes/generate`, `POST /qrcodes/{token}/revoke`).

**Belum ada / belum lengkap**
- Dashboard ringkas (rekap by jam/tanggal/status) — belum ada endpoint ringkas.
- Riwayat kehadiran kelas oleh pengurus kelas (per tanggal / per mapel) — belum ada endpoint khusus.
- Riwayat presensi siswa dengan filter tanggal — belum ada filter di `GET /me/attendance`.
- Info pelajaran hari ini untuk siswa (kelasnya sendiri) — belum ada endpoint khusus jadwal hari ini untuk siswa.

## Admin & Waka
**Sudah ada di backend**
- Admin CRUD master data (majors/classes/teachers/students/subjects/rooms/time-slots/school-years/semesters).
- Waka bulk jadwal per kelas per hari (`POST /classes/{class}/schedules/bulk`).
- Waka list/approve/reject izin (`GET /absence-requests`, `POST /absence-requests/{id}/approve`, `POST /absence-requests/{id}/reject`).
- Rekap & export presensi (`GET /attendance/recap`, `GET /attendance/export`).

**Belum ada / belum lengkap**
- Dashboard hasil absensi yang sudah diringkas (statistik ringkas per periode/per kelas) — baru ada rekap basic, belum ada endpoint dashboard khusus.

## Catatan Teknis
- Backend validasi status presensi menerima `dinas/izin`, tapi enum DB hanya `present, late, excused, sick, absent` (potensi mismatch).
- `GET /schedules` filter `date` memakai nama hari (Monday, Tuesday, dst). Mobile perlu kirim tanggal yang valid agar backend konversi ke nama hari.

