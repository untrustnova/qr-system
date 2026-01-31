# Pertimbangan Fitur: Mobile vs Desktop/Web (Backend)

Dokumen ini merangkum evaluasi backend untuk Mobile dan Desktop/Web.
Fokus: (1) perbandingan kesiapan backend Mobile vs Desktop/Web, (2) resiko/konflik endpoint.

## 1) Perbandingan Kesiapan Backend (Mobile vs Desktop/Web)

### Area yang sudah siap di kedua platform
- Autentikasi (login/logout/me) via Sanctum.
- Master data (admin CRUD) siap untuk kebutuhan Desktop/Web admin.
- Jadwal (GET /schedules) siap untuk admin/guru; siswa sudah punya /me/schedules.
- QR presensi (generate/revoke/scan) siap untuk admin/guru/pengurus kelas.
- Presensi dasar (scan, rekap, export, summary by schedule/class) sudah ada.
- Absence request (dispensation/sick/permit) tersedia; approve/reject untuk waka.

### Area yang lebih matang untuk Desktop/Web saat ini
- Admin features: CRUD lengkap (majors/classes/teachers/students/subjects/rooms/time-slots/school-years/semesters).
- Waka tools: bulk schedule + recap/export + approve/reject request.
- Endpoint tambahan yang sudah diimplementasi:
  - Waka summary dan per-student absences.
  - Admin view jadwal guru/kelas.
  - Wali kelas: rekap kelas per tanggal + summary per siswa.

### Area yang masih kurang untuk Mobile (terutama guru/siswa)
- Statistik guru (teaching summary) dan tindak lanjut siswa lintas kelas sudah diimplementasi, tetapi belum ada endpoint khusus “izin tidak mengajar”.
- Notifikasi harian otomatis belum ada (scheduler/push backend).
- Dashboard ringkas siswa (summary) sudah ada, tapi belum ada “today feed” selain /me/schedules.
- Riwayat kehadiran kelas oleh pengurus kelas (per mapel/tanggal) belum ada endpoint khusus selain by schedule.

### Area yang belum seimbang antara Mobile dan Desktop/Web
- Desktop/Waka mengharapkan CRUD kehadiran guru/siswa → baru ada `POST /attendance/manual` untuk admin type Waka.
- Mobile mengharapkan fitur refleksi/analitik guru yang lebih kaya → baru ada summary basic (per status + total).

## 2) Resiko / Konflik Endpoint & Data

### A. Inkonistensi status presensi
- Validasi `markExcuse` menerima `dinas/izin`, tapi enum DB hanya `present, late, excused, sick, absent`.
- Risiko: request dengan `izin/dinas` gagal di DB (error SQL) atau data tidak konsisten.
- Rekomendasi: samakan enum DB atau ubah validasi agar hanya enum yang valid.

### B. Jadwal berbasis hari vs tanggal
- Banyak endpoint jadwal memakai `day` (Monday, Tuesday, dll), sementara frontend cenderung pakai tanggal.
- Risiko: filter `from/to` di jadwal admin sekarang diterjemahkan ke nama hari, bukan rentang tanggal.
- Rekomendasi: tetapkan standar filter (pakai day-only atau simpan tanggal jadwal), dokumentasikan ke frontend.

### C. Data presensi per user (teacher vs student)
- Presensi guru tersimpan di tabel yang sama (`attendee_type=teacher`).
- Risiko: query summary lintas role bisa salah kalau filter `attendee_type` tidak konsisten.
- Rekomendasi: selalu filter `attendee_type` di seluruh endpoint summary.

### D. Realtime broadcast tanpa auth channel
- Broadcast channels yang dipakai adalah public `Channel` (bukan private).
- Risiko: data presensi/jadwal bisa di-subscribe tanpa auth (jika frontend expose channel).
- Rekomendasi: gunakan private channels + auth check, atau pastikan Pusher/socket layer dilindungi.

### E. Duplicate attendance rule
- Attendance scan menolak jika record sudah ada (tidak update).
- Risiko: jika guru input manual dulu lalu siswa scan, status tidak berubah otomatis.
- Rekomendasi: tentukan kebijakan (update otomatis/beri opsi override) dan sesuaikan flow mobile/web.

### F. Endpoint baru tanpa pagination
- Banyak endpoint summary/list baru menggunakan `get()` tanpa pagination.
- Risiko: payload besar (kelas besar / rentang tanggal panjang) bikin berat mobile/desktop.
- Rekomendasi: tambahkan pagination / limit, atau minimal filter tanggal wajib.

### G. Filter tanggal di /me/attendance
- Sudah ditambah filter `from/to/status`, tetapi status harus sesuai enum.
- Risiko: front-end kirim status non-enum (izin/dinas) → 422.
- Rekomendasi: selaraskan pilihan status di frontend.

### H. Role scope akses data
- Endpoint wali kelas mengecek `homeroomTeacher`.
- Risiko: jika guru bukan wali kelas tapi mengajar kelas tersebut, ia tidak bisa akses data kelas.
- Rekomendasi: pastikan kebutuhan business rule sudah sesuai (wali kelas-only vs guru pengajar).

---

## Catatan Penting (Mobile vs Desktop)
- Mobile fokus pada ringkas, cepat, dan notifikasi → backend masih belum punya layer notifikasi.
- Desktop/Web fokus pada manajemen data besar dan rekap → backend sudah lebih siap.
- Jika mobile butuh analitik detail (tindak lanjut siswa lintas kelas), endpoint sudah ada tapi belum ada caching/aggregation khusus.

