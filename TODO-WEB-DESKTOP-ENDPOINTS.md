# Usulan Endpoint Web/Desktop (Pelengkap Backend)

Dokumen ini berisi daftar endpoint yang disarankan agar fitur Web/Desktop berjalan penuh.

## Siswa
1) ✅ **Jadwal siswa sendiri**
- ✅ `GET /me/schedules`
- Query: `date` (YYYY-MM-DD, optional)
- Response: daftar jadwal kelas siswa untuk hari itu

2) ✅ **Daftar ketidakhadiran pribadi (filter tanggal)**
- ✅ Extend `GET /me/attendance`
- Query: `from`, `to`, `status`
- Response: list presensi terfilter

3) ✅ **Ringkasan ketidakhadiran pribadi**
- ✅ `GET /me/attendance/summary`
- Query: `from`, `to`
- Response: count per status + ringkas per hari

## Guru
1) **Riwayat kehadiran mengajar (guru)**
- `GET /me/attendance/teaching`
- Query: `from`, `to`, `status`
- Response: list presensi guru (by schedule)

2) **Statistik kehadiran mengajar**
- `GET /me/attendance/teaching/summary`
- Query: `from`, `to`
- Response: total per status + total sesi

3) **Tindak lanjut siswa lintas kelas**
- `GET /me/students/attendance-summary`
- Query: `from`, `to`, `threshold`
- Response: list siswa + count per status (absent/sick/permit/late)

## Wali Kelas
1) ✅ **Kehadiran siswa kelas (per tanggal)**
- ✅ `GET /classes/{class}/attendance`
- Query: `date`
- Response: daftar schedule pada tanggal tsb + presensi siswa

2) ✅ **Rekap siswa kelas (per siswa)**
- ✅ `GET /classes/{class}/students/attendance-summary`
- Query: `from`, `to`, `threshold`
- Response: list siswa + count per status

3) ✅ **Daftar ketidakhadiran siswa kelas**
- ✅ `GET /classes/{class}/students/absences`
- Query: `from`, `to`, `status`
- Response: daftar siswa dengan detail ketidakhadiran

## Waka
1) ✅ **Kehadiran guru per hari**
- ✅ `GET /attendance/teachers/daily`
- Query: `date`
- Response: list guru + status hadir (present/absent/late)

2) ✅ **CRUD kehadiran manual (guru/siswa)**
- ✅ `POST /attendance/manual`
- Payload: `attendee_type`, `student_id/teacher_id`, `schedule_id`, `status`, `date`, `reason`
- Response: attendance object

3) ✅ **Dashboard ringkas rekap**
- ✅ `GET /waka/attendance/summary`
- Query: `from`, `to`
- Response: total per status + top kelas/siswa bermasalah

4) ✅ **Daftar ketidakhadiran per siswa (global)**
- ✅ `GET /students/absences`
- Query: `from`, `to`, `status`, `class_id`
- Response: agregasi per siswa

## Admin
1) ✅ **Jadwal guru (view khusus)**
- ✅ `GET /teachers/{teacher}/schedules`
- Query: `from`, `to`
- Response: list jadwal guru

2) ✅ **Jadwal kelas (view khusus)**
- ✅ `GET /classes/{class}/schedules`
- Query: `from`, `to`
- Response: list jadwal kelas

## Catatan
- Semua endpoint di atas perlu policy role yang jelas (admin/teacher/student/waka).
- Disarankan tambahkan filter tanggal di endpoint list yang sudah ada agar frontend tidak terlalu berat.

---

## Tambahan Khusus Webta
Bagian ini melengkapi kebutuhan Webta (frontend web) yang belum tercakup di daftar atas.

### QR (Pengurus Kelas ↔ Guru)
1) **Generate QR token per jadwal (pengurus kelas)**
- `POST /me/class/qr-token`
- Payload: `schedule_id`, `expires_in` (optional)
- Response: `{ token, expires_at }`

2) **Scan/verify QR (guru)**
- `POST /attendance/scan`
- Payload: `token`
- Response: attendance object + status

3) **Revoke QR token (opsional)**
- `POST /qrcodes/{token}/revoke`

### Dokumen Bukti Izin/Sakit
1) **Upload bukti kehadiran**
- `POST /attendance/{attendance}/document`
- Form-data: `file`, `type`, `note` (optional)

2) **Ambil/preview bukti**
- `GET /attendance/{attendance}/document`

### Jadwal dalam Bentuk Gambar (Waka/Siswa)
1) **Upload gambar jadwal guru**
- `POST /teachers/{teacher}/schedule-image`
- Form-data: `file`

2) **Hapus gambar jadwal guru**
- `DELETE /teachers/{teacher}/schedule-image`

3) **Upload gambar jadwal kelas**
- `POST /classes/{class}/schedule-image`
- Form-data: `file`

4) **Hapus gambar jadwal kelas**
- `DELETE /classes/{class}/schedule-image`

5) **Ambil gambar jadwal kelas (untuk siswa)**
- `GET /classes/{class}/schedule-image`
