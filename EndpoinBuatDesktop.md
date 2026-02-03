# EndpoinBuatDesktop (Mapping Deskta -> API Backend)

Dokumen ini memetakan **fitur/halaman Deskta** ke **endpoint backend** (`routes/api.php`) + **detail UI** (filter, kolom, form) dan **gap/mismatch**.

Catatan global:
- Semua endpoint butuh `auth:sanctum` kecuali `POST /auth/login`.
- Pagination opsional (kirim `per_page` / `page`):
  - `GET /attendance/schedules/{schedule}`
  - `GET /me/students/attendance-summary`
  - `GET /classes/{class}/students/attendance-summary`
  - `GET /classes/{class}/students/absences`
  - `GET /students/absences`
  - `GET /attendance/teachers/daily`
  - `GET /teachers/{teacher}/schedules`
  - `GET /classes/{class}/schedules`
- Status presensi backend valid: `present, late, excused, sick, absent, dinas, izin`.
- Beberapa halaman Deskta memakai label status: `hadir, alpha, pulang, tanpa-keterangan, terlambat` → perlu mapping ke backend (lihat catatan mismatch per halaman).

---

## 1) Umum (Landing/Login)

### LandingPage / LoginPage
UI:
- Input: `login` (username/email), `password`.
- State: loading + error.

API:
- `POST /auth/login`
  - Body: `{ login, password }`
- `GET /me`
- `POST /auth/logout`

---

## 2) Admin

### DashboardAdmin (Beranda)
UI:
- Card statistik: Total Rombel, Total Murid, Total Guru, Total Lab, Ruang Teori.
- Quick access: Data Siswa, Data Guru, Data Kelas.

API (belum ada endpoint khusus):
- **Gap**: backend belum punya endpoint agregasi statistik admin.
- Sementara bisa dihitung dari:
  - `GET /classes` (total rombel)
  - `GET /students` (total murid)
  - `GET /teachers` (total guru)
  - `GET /rooms` (total lab/ruang) → butuh klasifikasi tipe lab/teori (belum ada field).

### GuruAdmin
UI:
- Filter: search (kode/nama/mapel/role), filter mapel, filter role.
- Table: `kodeGuru`, `namaGuru`, `mataPelajaran`, `role`.
- Action: edit, delete, view detail.
- Import CSV (file), Export PDF/CSV.
- Form Tambah/Ubah: `namaGuru`, `kodeGuru`, `role`, `mataPelajaran`.

API:
- `GET /teachers` (list)
- `POST /teachers` (create)
- `GET /teachers/{id}` (detail)
- `PUT /teachers/{id}` (update)
- `DELETE /teachers/{id}` (delete)
- `POST /teachers/import` (bulk)

Mismatch:
- Backend **butuh**: `name, username, password, nip, subject, homeroom_class_id, phone`.
- UI hanya punya `kodeGuru (→ nip)`, `namaGuru (→ name)`, `mataPelajaran (→ subject)`, `role` (tidak ada field backend), **tanpa** `username/email/password`.

### SiswaAdmin
UI:
- Filter: jurusan, kelas, search (nama/nisn).
- Table: `namaSiswa`, `nisn`, `jurusan`, `kelas`, `jenisKelamin`.
- Action: edit, delete, view detail.
- Import CSV: kolom `Nama Siswa, NISN, Jenis Kelamin, Jurusan, Kelas, No Telp, Password`.
- Export PDF/CSV.
- Form Tambah/Ubah: `namaSiswa`, `nisn`, `jurusanId`, `kelasId`.

API:
- `GET /students`
- `POST /students`
- `GET /students/{id}`
- `PUT /students/{id}`
- `DELETE /students/{id}`
- `POST /students/import`

Mismatch:
- Backend **butuh**: `name, username, password, nisn, nis, gender, address, class_id`.
- UI belum minta `username`, `nis`, `address`.
- Gender di UI `Laki-Laki/Perempuan` → backend `L/P`.

### KelasAdmin
UI:
- Filter: konsentrasi keahlian, tingkat kelas.
- Table: `konsentrasiKeahlian`, `tingkatKelas`, `namaKelas`, `waliKelas`.
- Form Tambah/Ubah: `namaKelas`, `jurusanId`, `kelasId`, `waliKelasId`.

API:
- `GET /classes`
- `POST /classes`
- `GET /classes/{id}`
- `PUT /classes/{id}`
- `DELETE /classes/{id}`

Mismatch:
- Backend class fields: `grade`, `label`, `major_id`.
- `waliKelasId` **bukan** field class → harus update `teacher.homeroom_class_id` via `PUT /teachers/{id}`.

### JurusanAdmin (Konsentrasi Keahlian)
UI:
- Search: kode/nama.
- Table: `kode`, `nama`.
- Form: `kodeJurusan`, `namaJurusan`.

API:
- `GET /majors`
- `POST /majors` (code, name, optional category)
- `GET /majors/{id}`
- `PUT /majors/{id}`
- `DELETE /majors/{id}`

Mismatch:
- UI belum punya `category`.

### DetailGuru
UI:
- Field: `nama`, `nip`, `jenisKelamin`, `peran`, `noTelp`, `password`.
- Edit modal: `jenisKelamin`, `peran`, `noTelp`, `password`.

API:
- `GET /teachers/{id}`
- `PUT /teachers/{id}`

Mismatch:
- Backend tidak punya `jenisKelamin` / `peran`.

### DetailSiswa
UI:
- Field: `namaSiswa`, `nisn`, `jenisKelamin`, `noTelp`, `jurusan`, `kelas`, `tahunAngkatan`, `password`.
- Edit modal: `jenisKelamin`, `noTelp`, `tahunAngkatan`, `password`.

API:
- `GET /students/{id}`
- `PUT /students/{id}`

Mismatch:
- Backend tidak punya `tahunAngkatan`.

### Master Data (belum ada UI Deskta)
- `rooms`, `subjects`, `time-slots`, `school-years`, `semesters`.

---

## 3) Waka Staff (admin-type: waka)

### DashboardStaff
UI:
- Grafik harian & bulanan (kategori: hadir, izin, sakit, pulang, tidak_hadir).
- Stat card: tepat waktu, terlambat, izin, sakit, pulang.

API:
- `GET /waka/attendance/summary` (status/class/student)

Gap:
- Backend belum punya **trend daily/monthly** + kategori `pulang`.

### JadwalKelasStaff
UI:
- Filter: jurusan, tingkat.
- Table: `namaKelas`, `jurusan`, `waliKelas`.
- Aksi: lihat detail jadwal (gambar), upload gambar jadwal.

API:
- `GET /classes` (untuk list + filter)
- `GET /classes/{class}/schedules`
- `POST /classes/{class}/schedules/bulk`

Gap:
- Upload jadwal **gambar** belum ada endpoint.

### JadwalGuruStaff
UI:
- Search: kode guru/nama/mapel/role.
- Table: `kodeGuru`, `namaGuru`, `mataPelajaran`, `role`.
- Aksi: lihat detail jadwal (gambar), upload gambar.

API:
- `GET /teachers`
- `GET /teachers/{teacher}/schedules`

Gap:
- Upload jadwal **gambar** belum ada endpoint.

### LihatGuru / LihatKelas
UI:
- Menampilkan jadwal sebagai gambar.

Gap:
- Backend tidak menyimpan jadwal dalam format gambar.

### KehadiranGuru
UI:
- Filter: search nama guru, tanggal (date).
- Table: `namaGuru`, `jadwal`, `status bar 1-10 jam`.
- Aksi: view detail.

API:
- `GET /attendance/teachers/daily?date=YYYY-MM-DD`

Gap:
- Endpoint hanya memberi 1 status per guru/hari, tidak per jam (1-10).

### DetailKehadiranGuru
UI:
- Table: `tanggal`, `jam`, `mapel`, `kelas`, `status` (editable).

Gap:
- Tidak ada endpoint detail per guru untuk Waka.

### KehadiranSiswa
UI:
- Filter: jurusan, tingkat kelas.
- Table: `namaKelas`, `namaJurusan`, `waliKelas` → klik detail.

API:
- `GET /classes` (list kelas + jurusan)

### DetailSiswaStaff
UI:
- Filter: mapel, tanggal.
- Table: `nisn`, `namaSiswa`, `mataPelajaran`, `status` (editable).
- Summary: hadir/izin/sakit/tidak-hadir.
- Export CSV/PDF.

API:
- `GET /students/absences?class_id=...` (rekap per siswa)

Gap:
- Endpoint belum menyediakan **rekap per mapel + tanggal**.
- Edit status butuh `attendance_id` → UI tidak punya `attendance_id`.

### Approval Izin/Dispensasi
UI: belum ada halaman khusus di deskta, tapi tersedia di backend.

API:
- `GET /absence-requests`
- `POST /absence-requests/{id}/approve`
- `POST /absence-requests/{id}/reject`

---

## 4) Guru

### GuruDashboard
UI:
- Card: user info, tanggal/waktu, total mengajar hari ini.
- List jadwal hari ini: `mapel`, `kelas`, `jam`.
- Aksi: QR / view / modal metode / laporan tidak bisa mengajar.

API:
- `GET /schedules` (jadwal guru)
- `POST /qrcodes/generate` (buat QR)

Gap:
- Laporan “Tidak bisa mengajar” belum ada endpoint.

### MetodeGuru (Modal)
UI:
- Pilih QR / Manual / (opsional) Dispensasi.
- Dispensasi fields: alasan, tanggal, jam mulai/selesai, keterangan, bukti (file).

Gap:
- Tidak ada endpoint khusus dispensasi guru.

### InputManualGuru
UI:
- Date picker, kelas, mapel.
- Table: `nisn`, `nama`, radio status (`hadir`, `sakit`, `izin`, `tidak hadir`).
- Simpan.

API:
- `POST /attendance/manual` **(hanya admin-type Waka)**

Gap:
- Guru tidak punya akses manual input.

### KehadiranSiswaGuru
UI:
- Info tanggal, kelas, mapel.
- Table: `nisn`, `nama`, `mapel`, `status` (edit modal).
- Status: `hadir, izin, sakit, alpha, pulang`.

API:
- `GET /attendance/schedules/{schedule}`
- `POST /attendance/{attendance}/excuse`

Mismatch:
- `alpha`/`pulang` tidak ada di backend.

### DetailJadwalGuru
UI:
- Jadwal grid per hari/jam + kelas dropdown.

API:
- `GET /schedules?class_id=...` (admin) atau `GET /schedules` (guru)

Gap:
- API belum menyediakan grid terstruktur per jam (but can build from schedules).

---

## 5) Wali Kelas (teacher)

### DashboardWalliKelas
UI:
- Card statistik, list jadwal, modals (metode/absen/tidak bisa mengajar).

API:
- `GET /classes/{class}/students/attendance-summary`
- `GET /classes/{class}/attendance` (by date)

Gap:
- “Tidak bisa mengajar” endpoint belum ada.

### KehadiranSiswaWakel
UI:
- Filter: periode tanggal, mapel.
- Table: `nisn`, `nama`, `mapel`, `tanggal`, `status`.
- Summary: pulang/izin/sakit/tanpa-keterangan.
- Edit status.
- Buat perizinan (form: nisn, nama, alasan, mapel, nama guru, tanggal, keterangan, file).

API:
- `GET /classes/{class}/students/absences`
- `GET /classes/{class}/attendance?date=...`
- `POST /attendance/{attendance}/excuse`
- `POST /absence-requests` (perizinan)

Mismatch:
- Status `pulang`, `tanpa-keterangan` tidak ada di backend.
- Form perizinan perlu mapping ke `type` (`permit/sick/dispensation`) + `start_date/end_date`.

### RekapKehadiranSiswa
UI:
- Filter: periode tanggal, search nisn/nama.
- Table: `nisn`, `nama`, `hadir`, `sakit/izin`, `alpha`, `pulang`, `status aktif`.
- Export CSV.

API:
- `GET /classes/{class}/students/attendance-summary`
- `GET /classes/{class}/students/absences`

Mismatch:
- `pulang` tidak ada status backend.

### InputAbsenWalikelas
UI:
- Manual input status siswa (mirip InputManualGuru).

API:
- `POST /attendance/manual` **(hanya admin-type Waka)** → gap.

### JadwalPengurus (di WaliKelas)
UI:
- Menampilkan jadwal sebagai gambar.

Gap:
- `GET /classes/{class}/schedules` hanya admin.

---

## 6) Pengurus Kelas (student + class-officer)

### DashboardPengurusKelas
UI:
- Statistik bulanan/weekly (hadir/izin/sakit/alpha/dispen).
- Jadwal ringkas hari ini.

API:
- `GET /me/attendance/summary`
- `GET /me/schedules`

Mismatch:
- `dispen` tidak ada status backend (bisa mapping ke `excused/izin`).

### JadwalPengurus
UI:
- Jadwal kelas (card/list)

API:
- `GET /me/schedules`

### DaftarMapel
UI:
- List mapel/hari ini.
- Generate QR (gunakan QR eksternal saat ini).

API:
- `GET /me/schedules`
- `POST /qrcodes/generate` (type=student, schedule_id)
- `POST /qrcodes/{token}/revoke`

### TidakHadirPenguruskelas
UI:
- Filter: date range, status (alpha/izin/sakit/pulang).
- Table: `tanggal`, `jamPelajaran`, `mataPelajaran`, `guru`, `status`.
- Modal detail keterangan.

API:
- `GET /me/attendance?from&to&status`
- `POST /absence-requests` (untuk izin/dispensasi)

Mismatch:
- Status `alpha`/`pulang` tidak ada di backend.

---

## 7) Siswa

### DashboardSiswa
UI:
- Statistik bulanan & weekly: hadir/izin/sakit/alpha/dispen.
- Jadwal hari ini (mapel, guru, jam).

API:
- `GET /me/attendance/summary`
- `GET /me/schedules`

Mismatch:
- `dispen` tidak ada di backend (mapping ke `excused/izin`).

### JadwalSiswa
UI:
- Jadwal berupa gambar.

API:
- `GET /me/schedules` (jadwal list) → perlu rendering table sendiri.

Gap:
- Tidak ada jadwal “image”.

### AbsensiSiswa
UI:
- Filter: date range, status (hadir/tidak hadir/izin/sakit/pulang).
- Table: tanggal, jam, mapel, guru, status.
- Modal detail status (keterangan).

API:
- `GET /me/attendance?from&to&status`

Mismatch:
- Status `alpha`/`pulang` tidak ada di backend.

---

## 8) Ringkasan Gap / Mismatch Utama

1) **Manual attendance** (`InputManualGuru`, `InputAbsenWalikelas`) butuh akses `POST /attendance/manual` → saat ini hanya admin-type Waka.
2) **Status mismatch**: UI pakai `alpha/pulang/tanpa-keterangan/terlambat` vs backend `present/late/absent/izin/dinas/sick/excused`.
3) **Laporan tidak bisa mengajar** (guru/wali) belum ada endpoint.
4) **Jadwal dalam bentuk gambar** (Waka/Siswa) belum ada backend.
5) **Dashboard trend** (daily/monthly) belum ada endpoint khusus.

