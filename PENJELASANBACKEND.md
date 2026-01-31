# Penjelasan Backend QR System

Dokumen ini merangkum fitur dan alur backend yang sudah ada berdasarkan kode di repository ini (routes + controllers + models + middleware).

## Gambaran Umum
- Backend menggunakan Laravel (struktur `routes/`, `app/Http/Controllers`, `app/Models`).
- Autentikasi API memakai Laravel Sanctum (token `createToken('api')`).
- Akses endpoint dibatasi oleh middleware role (`role`), admin type (`admin-type`), dan class officer (`class-officer`).
- Aktivitas API write (POST/PUT/PATCH/DELETE) dicatat oleh middleware `activity`.
- Ada broadcasting event untuk QR/attendance/absence/schedules (channel publik), untuk update realtime.

## Role & Hak Akses
Role utama di `user_type`: `admin`, `teacher`, `student`.
- **role**: memastikan user bertipe sesuai.
- **admin-type**: memastikan admin punya tipe tertentu (contoh: `waka`).
- **class-officer**: memastikan siswa adalah pengurus kelas (`is_class_officer`).

## Autentikasi & Profil
**Endpoint**
- `POST /auth/login` → login, return token + user + profile.
- `GET /me` → profile user aktif (dengan admin/teacher/student profile).
- `POST /auth/logout` → revoke token aktif.

**Flow singkat**
1. User login (username/email + password).
2. Sistem cek password & status aktif.
3. Jika admin belum punya adminProfile, dibuat otomatis dengan `type = waka`.
4. Token Sanctum dibuat dan dikembalikan.

## Master Data (Admin)
Semua endpoint berikut hanya untuk `role:admin`.
- **Majors**: `majors` CRUD
- **Classes**: `classes` CRUD
- **Teachers**: `teachers` CRUD + import massal
- **Students**: `students` CRUD + import massal
- **School Years**: `school-years` CRUD
- **Semesters**: `semesters` CRUD
- **Rooms**: `rooms` CRUD
- **Subjects**: `subjects` CRUD
- **Time Slots**: `time-slots` CRUD

**Catatan**: import guru/siswa membuat user + profile dalam 1 transaksi.

## Jadwal (Schedules)
**Akses**
- Admin: full CRUD (kecuali index/show via route admin).
- Admin & Teacher: bisa `index` dan `show`.
- Admin (tipe `waka`): bulk update jadwal satu kelas per hari.

**Endpoint utama**
- `GET /schedules` → list jadwal (filter `class_id`, `date`).
- `GET /schedules/{schedule}` → detail jadwal.
- `POST /schedules` → buat jadwal (admin).
- `PUT/PATCH /schedules/{schedule}` → update (admin).
- `DELETE /schedules/{schedule}` → delete (admin).
- `POST /classes/{class}/schedules/bulk` → replace jadwal harian (admin type `waka`).

**Flow**
- `index`: guru hanya melihat jadwalnya sendiri.
- `show`: guru hanya boleh melihat jadwal miliknya.
- `bulkUpsert`: hapus jadwal lama pada hari/semester/tahun tertentu, lalu buat baru; memicu event `schedules.bulk-updated`.

## QR Code (Sesi Presensi)
**Akses**
- Admin & Teacher: generate/revoke QR.
- Student (class officer): generate/revoke QR hanya untuk kelasnya dan tipe `student`.

**Endpoint**
- `GET /qrcodes/active` → list QR aktif.
- `POST /qrcodes/generate` → buat QR untuk schedule.
- `POST /qrcodes/{token}/revoke` → cabut QR.

**Flow Generate**
1. Validasi schedule + tipe QR (`student`/`teacher`).
2. Otorisasi:
   - Guru hanya bisa buat QR untuk jadwalnya atau kelas yang diawali sebagai wali.
   - Siswa harus pengurus kelas, dan hanya untuk kelasnya sendiri.
3. QR dibuat (token UUID, expires default 15 menit).
4. Event `qr.generated` dibroadcast.

**Flow Revoke**
- Otorisasi serupa generate, lalu `is_active=false` dan status jadi `expired`.

## Presensi (Attendance)
**Akses**
- Scan: admin/teacher/student.
- Rekap/export/ubah status: admin/teacher.
- Siswa: bisa lihat riwayat presensi dirinya.

**Endpoint**
- `POST /attendance/scan` → scan QR dan catat presensi.
- `GET /attendance/schedules/{schedule}` → list presensi per jadwal.
- `POST /attendance/{attendance}/excuse` → ubah status presensi.
- `POST /attendance/{attendance}/void` → batalkan (hapus) record presensi.
- `POST /attendance/{attendance}/attachments` → upload bukti.
- `GET /attendance/recap` → rekap per bulan.
- `GET /attendance/schedules/{schedule}/summary` → summary status per jadwal.
- `GET /attendance/classes/{class}/summary` → summary status per kelas.
- `GET /attendance/export` → export CSV.
- `GET /me/attendance` → list presensi siswa sendiri.

**Flow Scan**
1. Validasi token QR + device_id (khusus siswa).
2. Cek QR aktif dan belum expired.
3. Validasi role sesuai jenis QR (student/teacher).
4. Validasi device siswa harus terdaftar & aktif.
5. Validasi kelas/guru sesuai schedule QR.
6. Jika record attendance sudah ada → return "Presensi sudah tercatat" (tidak update).
7. Jika belum ada → buat attendance dengan status `present`, source `qrcode`, checked_in_at = now.
8. Broadcast event `attendance.recorded`.

**Flow Ubah Status**
- `markExcuse` menerima status: `late, excused, sick, absent, present, dinas, izin`.
- Update status + reason + source = `manual`.

**Catatan status DB**
- Enum status di migration: `present, late, excused, sick, absent`.
- Ada perbedaan dengan validation `markExcuse` yang menerima `dinas/izin` (potensi mismatch DB).

## Izin/Dispensasi (Absence Requests)
**Akses**
- Semua role dapat membuat request.
- Hanya admin type `waka` yang dapat list/approve/reject.

**Endpoint**
- `POST /absence-requests` → buat request (dispensation/sick/permit).
- `GET /absence-requests` → list (admin type waka).
- `POST /absence-requests/{id}/approve` → set approved.
- `POST /absence-requests/{id}/reject` → set rejected.

**Flow**
1. Request dibuat dengan status `pending`.
2. Otorisasi:
   - Siswa harus pengurus kelas untuk mengajukan.
   - Guru hanya boleh ajukan untuk kelas yang dia wali atau dia ajar.
3. Admin `waka` memproses approve/reject.
4. Event `absence.requested` dan `absence.updated` dibroadcast.

## Device (Siswa)
**Akses**
- Siswa dapat daftar/hapus device sendiri.

**Endpoint**
- `POST /me/devices` → register device (aktifkan satu device).
- `DELETE /me/devices/{device}` → hapus device.

**Flow**
- Saat register device untuk siswa, semua device lain dinonaktifkan → hanya 1 device aktif.

## WhatsApp Integration (Admin)
**Endpoint**
- `POST /wa/send-text`
- `POST /wa/send-media`

**Flow**
- Mengirim request ke provider WA berdasarkan konfigurasi `services.whatsapp`.
- Jika konfigurasi belum ada, return 501.

## Web Routes (Simple View)
- `/login` (GET/POST) dan `/logout` (POST) untuk autentikasi web.
- `/schedules` halaman daftar jadwal (admin/teacher).

## Broadcast Events
- `qr.generated` (channel `schedules.{schedule_id}`)
- `attendance.recorded` (channel `schedules.{schedule_id}`)
- `absence.requested` (channel `waka.absence-requests` & `classes.{class_id}`)
- `absence.updated` (channel sama)
- `schedules.bulk-updated` (channel `classes.{class_id}`)

## Ringkas Alur Utama
1. **Login** → token → akses API sesuai role.
2. **Admin** setup master data (majors/classes/teachers/students/subjects/rooms/time-slots/school-years/semesters).
3. **Jadwal** dibuat admin, guru bisa melihat jadwalnya; waka bisa bulk.
4. **QR** dibuat admin/guru/pengurus kelas untuk sesi presensi.
5. **Scan** oleh siswa/guru → attendance tercatat.
6. **Rekap** & **Export** bisa diakses admin/guru.
7. **Absence request** diajukan → diproses admin waka.

