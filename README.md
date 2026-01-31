# QR Attendance API (Laravel 12 + Sail/Octane/Reverb)

API-only QR-based attendance system for sekolah roles (admin, waka/kesiswaan, guru, siswa). Stack includes Laravel Octane, Reverb broadcasting, Sanctum auth tokens, Telescope observability, Simple QrCode, and CSV/Excel-friendly exports.

## Quick start
- Copy env & deps: `cp .env.example .env` (or use existing), `composer install`, `bun install`
- Boot Sail (MariaDB): `./vendor/bin/sail up -d`
- Generate key & migrate: `./vendor/bin/sail artisan key:generate && ./vendor/bin/sail artisan migrate`
- Run dev stack (Octane + queue + Vite): `./vendor/bin/sail artisan octane:start --watch --host=0.0.0.0 --port=8000`
- (Optional) Telescope: `./vendor/bin/sail artisan telescope:install && ./vendor/bin/sail artisan migrate`

## Web Access
- Login page: `http://localhost:8000/login`
- Schedules view (admin/waka/guru only): `http://localhost:8000/schedules`
- API docs: `http://localhost:8000/docs`

## Tips
- Perubahan `.env` butuh restart service: `./vendor/bin/sail restart laravel.test`.
- Jika port bentrok, cek container yang memakai port: `docker ps --format "table {{.Names}}\\t{{.Ports}}"`.
- Untuk reset data: `./vendor/bin/sail artisan migrate:fresh --seed`.
- Log aktivitas ada di `storage/logs/laravel.log` dan stderr jika `LOG_STACK=single,stderr`.

## .env Examples
- Sail:
  - `DB_HOST=mariadb`
  - `DB_DATABASE=db_qr_system`
  - `DB_USERNAME=sail`
  - `DB_PASSWORD=password`
- Non-Sail (local MySQL):
  - `DB_HOST=127.0.0.1`
  - `DB_DATABASE=qr_system`
  - `DB_USERNAME=root`
  - `DB_PASSWORD=secret`

## Docs Shortcuts
- `README.md`
- `REQUESTJSON.md`
- `CHANGES.md`
- `TODO.md`
- `public/docs/openapi.json`

## Core domain
- Users (`user_type`: admin|teacher|student) with profiles: `admin_profiles`, `teacher_profiles` (NIP, homeroom, subject), `student_profiles` (NISN, NIS, class, gender, address).
- Majors (jurusan) and classes (major_id, grade, label).
- Schedules (link teacher+class, semester/year/time, optional subject_name).
- QR sessions (`qrcodes`): token, type (student|teacher), schedule, issued_by, expire/active.
- Attendance: attendee_type (student|teacher), status (present/late/excused/sick/absent), checked_in_at, schedule, QR link.
- Absence requests: dispensasi/sick/permit approval flow (Waka approval + signature).

## API (routes/api.php)
- Auth: `POST /auth/login` (username/email + password) → token; `GET /me`; `POST /auth/logout`.
- Admin-only CRUD: `/majors`, `/classes`, `/teachers`, `/students`, `/schedules` (create/update/delete).
- Waka-only: bulk schedules `POST /classes/{class}/schedules/bulk`, approval `POST /absence-requests/{id}/approve|reject`.
- Admin/Teacher: `GET /schedules`, `GET /schedules/{id}`; QR lifecycle `GET /qrcodes/active`, `POST /qrcodes/generate` (schedule_id, type, optional expires), `POST /qrcodes/{token}/revoke`.
- Admin/Teacher/Class Officer: `POST /absence-requests` (pengajuan dispensasi/sick/permit).
- Attendance:
  - Scan QR (student/teacher) `POST /attendance/scan` with `token`.
  - View per schedule `GET /attendance/schedules/{schedule}` (admin/teacher).
  - Mark excuse/status `POST /attendance/{attendance}/excuse` (admin/teacher).
  - Export CSV `GET /attendance/export?schedule_id=` (admin/teacher).
- WhatsApp (admin-only): `POST /wa/send-text`, `POST /wa/send-media` (requires provider config).
All protected with Sanctum; role guard via middleware `role:{admin|teacher|student}`.

## Real-time (Reverb)
- Channels: `schedules.{id}`, `classes.{id}`, `waka.absence-requests`.
  - `qr.generated` payload: token, type, schedule_id, expires_at.
  - `attendance.recorded` payload: attendee_type, schedule_id, status, name.
  - `schedules.bulk-updated` payload: class_id, day, semester, year, count.
  - `absence.requested` and `absence.updated` payloads for Waka approval flow.

## Flows (ringkas)
- Admin input guru/siswa/kelas/jurusan/jadwal → sistem siap.
- Waka kelola jadwal per hari (bulk) dan approve izin/dispensasi dengan tanda tangan.
- Pengurus kelas bisa generate/revoke QR siswa untuk kelasnya.
- Guru melihat jadwal, generate QR sesi, siswa scan untuk presensi, guru scan/klik hadir di akhir jam.
- Rekap dapat diekspor CSV; monitoring real-time via Reverb.

## User flow per role (detail)
### 1) Admin (role: admin)
- Login via `/auth/login` (API) atau `/login` (web) lalu ambil token.
- Kelola master data: jurusan, kelas, guru, siswa, tahun ajaran, semester, ruang, mata pelajaran, jam pelajaran.
- Kelola jadwal pelajaran (create/update/delete).
- Lihat jadwal (`GET /schedules`, `GET /schedules/{id}`).
- Generate/revoke QR sesi (guru/kelas) via `/qrcodes/generate` dan `/qrcodes/{token}/revoke`.
- Lihat presensi per jadwal, rekap, export CSV; bisa ubah status/void/attach bukti.
- Kirim notifikasi WhatsApp (opsional, bila provider tersedia).

### 2) Waka/Kesiswaan (role: admin + admin-type: waka)
- Semua akses admin di atas, plus:
- Bulk input jadwal per kelas (`POST /classes/{class}/schedules/bulk`).
- Review daftar pengajuan izin/sakit/dispensasi.
- Approve/Reject pengajuan dan simpan tanda tangan persetujuan.

### 3) Guru (role: teacher)
- Login, lihat jadwal mengajar sendiri.
- Generate QR untuk sesi mengajar (untuk siswa scan) dan revoke bila perlu.
- Lihat presensi per jadwal, rekap kelas, dan export CSV.
- Tandai status presensi (excuse/void) dan lampirkan bukti bila dibutuhkan.
- Ajukan izin/sakit/dispensasi untuk siswa (sebagai pengusul).

### 4) Siswa (role: student)
- Login dan cek presensi pribadi (`GET /me/attendance`).
- Daftarkan perangkat aktif (1 perangkat) untuk scan QR (`POST /me/devices`).
- Scan QR saat kelas berlangsung (`POST /attendance/scan`).
- Ajukan izin/sakit/dispensasi untuk diri sendiri.

### 5) Pengurus Kelas (role: student + class-officer)
- Semua alur siswa di atas, plus:
- Generate/revoke QR kelas (khusus QR siswa) untuk membantu presensi kelas.

## API Docs
- Scalar UI: `/docs` (reads `public/docs/openapi.json`).

## WhatsApp Provider
- Set `WHATSAPP_API_BASE_URL` and optional `WHATSAPP_API_TOKEN` in `.env`.

## Testing
- Run tests locally: `php artisan test`.
- GitHub Actions CI runs tests on SQLite using `.github/workflows/ci.yml`.
- Add seeds/factories for roles before manual testing; use Postman/Thunder Client with Bearer token from `/auth/login`.
