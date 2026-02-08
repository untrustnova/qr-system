# Spesifikasi Endpoint Mobile App

Dokumen ini berisi spesifikasi JSON Request/Response yang dibutuhkan oleh Mobile App agar sesuai dengan tampilan UI saat ini.

---

## 1. Authentication

### Login
**Endpoint**: `POST /api/auth/login`

**Request**:
```json
{
    "email": "siswa@sekolah.sch.id",
    "password": "password123",
    "device_name": "Samsung A50"
}
```

**Response (200 OK)**:
```json
{
    "message": "Login successful",
    "token": "1|laravel_sanctum_token_string...",
    "user": {
        "id": 1,
        "name": "Budi Santoso",
        "email": "siswa@sekolah.sch.id",
        "role": "student",
        "is_class_officer": true,
        "profile": {
            "nis": "123456",
            "class_name": "XII RPL 1",
            "photo_url": "https://..."
        }
    }
}
```

**Response (401 Unauthorized)**:
```json
{
    "message": "Invalid credentials"
}
```

---

## 2. Dashboard Siswa

### Get Dashboard Summary (Jadwal Hari Ini + Status Absensi)
**Endpoint**: `GET /api/me/dashboard/summary`  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "date": "2025-02-05",
    "day_name": "Rabu",
    "student": {
        "name": "Budi Santoso",
        "class_name": "XII RPL 1",
        "nis": "123456",
        "photo_url": "https://...",
        "is_class_officer": true
    },
    "school_hours": {
        "start_time": "07:00",
        "end_time": "15:00"
    },
    "schedule_today": [
        {
            "id": 101,
            "time_slot": "Jam Ke 1-3",
            "subject": "Bahasa Indonesia",
            "teacher": "Pak Guru A",
            "start_time": "07:00",
            "end_time": "09:30",
            "status": "present",
            "status_label": "Hadir Tepat Waktu",
            "check_in_time": "07:05"
        },
        {
            "id": 102,
            "time_slot": "Jam Ke 4-6",
            "subject": "Matematika",
            "teacher": "Bu Guru B",
            "start_time": "09:45",
            "end_time": "12:00",
            "status": "none",
            "status_label": "Belum Absen",
            "check_in_time": null
        }
    ]
}
```

---

## 3. Dashboard Guru

### Get Teacher Dashboard Summary
**Endpoint**: `GET /api/me/dashboard/teacher-summary`  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "date": "2025-02-05",
    "day_name": "Rabu",
    "teacher": {
        "name": "Pak Guru A",
        "nip": "19800101...",
        "code": "G001",
        "photo_url": "https://..."
    },
    "school_hours": {
        "start_time": "07:00",
        "end_time": "15:00"
    },
    "attendance_summary": {
        "present": 25,
        "sick": 1,
        "excused": 1,
        "absent": 3
    },
    "schedule_today": [
        {
            "id": 201,
            "subject": "Matematika",
            "class_name": "XII RPL 1",
            "time_slot": "Jam Pertama",
            "start_time": "07:30",
            "end_time": "08:15"
        },
        {
            "id": 202,
            "subject": "Bahasa Indonesia",
            "class_name": "XII RPL 2",
            "time_slot": "Jam Kedua",
            "start_time": "08:15",
            "end_time": "09:00"
        }
    ]
}
```

---

## 4. Riwayat Kehadiran Guru

### Get Teacher Attendance History
**Endpoint**: `GET /api/me/attendance/teaching`  
**Query Params**: `?date=2025-02-05&status=present` (optional)  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 301,
            "subject": "Matematika",
            "class_name": "XII RPL 1",
            "date": "05-02-2025",
            "time": "07:30",
            "status": "present",
            "status_label": "Hadir Tepat Waktu"
        },
        {
            "id": 302,
            "subject": "Bahasa Indonesia",
            "class_name": "XII RPL 2",
            "date": "05-02-2025",
            "time": "09:15",
            "status": "late",
            "status_label": "Hadir Terlambat"
        }
    ],
    "summary": {
        "present": 15,
        "sick": 1,
        "excused": 1,
        "absent": 0
    }
}
```

---

## 5. Tindak Lanjut Siswa (Guru)

### Get Students Requiring Follow-up
**Endpoint**: `GET /api/me/students/follow-up`  
**Query Params**: `?search=budi` (optional)  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Budi Santoso",
            "nis": "123456",
            "class_name": "XII RPL 1",
            "attendance_summary": {
                "absent": 3,
                "excused": 7,
                "sick": 1
            },
            "badge": {
                "type": "danger",
                "label": "Sering Absensi"
            },
            "severity_score": 310
        },
        {
            "id": 2,
            "name": "Cindy Permata",
            "nis": "123457",
            "class_name": "XII RPL 2",
            "attendance_summary": {
                "absent": 0,
                "excused": 8,
                "sick": 2
            },
            "badge": {
                "type": "warning",
                "label": "Perlu Diperhatikan"
            },
            "severity_score": 82
        }
    ]
}
```

**Badge Logic**:
- `danger` (Sering Absensi): `absent >= 1`
- `warning` (Perlu Diperhatikan): `excused > 5 && absent == 0`
- `success` (Aman): `excused <= 5 && absent == 0`

---

## 6. Notifikasi Guru

### Get Teacher Notifications
**Endpoint**: `GET /api/me/notifications`  
**Query Params**: `?date=2025-02-05` (optional, default: today)  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "date": "2025-02-05",
    "notifications": [
        {
            "id": 1,
            "type": "tepat_waktu",
            "message": "Anda mengajar tepat waktu pada",
            "detail": "Pelajaran Matematika-XII RPL 1",
            "time": "07:30",
            "created_at": "2025-02-05 07:30:00"
        },
        {
            "id": 2,
            "type": "alpha_siswa",
            "message": "Ada siswa alpha pada kelas",
            "detail": "XII Mekatronika 1 - Mapel Fisika",
            "time": "11:00",
            "created_at": "2025-02-05 11:00:00"
        }
    ]
}
```

**Notification Types**:
- `tepat_waktu`: Guru hadir tepat waktu
- `terlambat`: Guru terlambat
- `alpha_siswa`: Ada siswa alpha
- `tindak_lanjut`: Perlu tindak lanjut siswa
- `izin_siswa`: Permohonan izin siswa
- `rapor_kehadiran`: Rapor kehadiran tersedia
- `reminder`: Reminder jadwal besok

---

## 7. QR Code & Absensi

### Scan QR Code
**Endpoint**: `POST /api/attendance/scan`  
**Header**: `Authorization: Bearer <token>`

**Request**:
```json
{
    "qrcode_token": "random_string_from_qr",
    "latitude": -6.12345,
    "longitude": 106.12345
}
```

**Response (200 OK)**:
```json
{
    "message": "Presensi berhasil dicatat",
    "status": "present",
    "timestamp": "2025-02-05 07:15:00",
    "schedule": {
        "subject": "Matematika",
        "class_name": "XII RPL 1",
        "teacher": "Pak Guru A"
    }
}
```

**Response (422 Unprocessable Entity)**:
```json
{
    "message": "QR Code sudah tidak valid atau kadaluarsa"
}
```

---

## 8. Riwayat Kehadiran Siswa

### Get Student Attendance History
**Endpoint**: `GET /api/me/attendance`  
**Query Params**: `?month=2&year=2025` (optional)  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 401,
            "date": "2025-02-04",
            "subject": "Matematika",
            "status": "present",
            "status_label": "Hadir",
            "check_in_time": "07:05",
            "attachment_url": null
        },
        {
            "id": 402,
            "date": "2025-02-03",
            "subject": "Bahasa Inggris",
            "status": "sick",
            "status_label": "Sakit",
            "check_in_time": null,
            "attachment_url": "https://..."
        }
    ],
    "summary": {
        "present": 20,
        "late": 2,
        "sick": 1,
        "excused": 1,
        "absent": 0
    }
}
```

---

## 9. Dashboard Wali Kelas

### Get Homeroom Teacher Dashboard
**Endpoint**: `GET /api/me/homeroom/dashboard`  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "date": "2025-02-05",
    "homeroom_class": {
        "id": 1,
        "name": "XII RPL 1",
        "total_students": 30
    },
    "attendance_summary": {
        "present": 25,
        "late": 2,
        "sick": 1,
        "excused": 1,
        "absent": 1
    },
    "schedule_today": [
        {
            "id": 501,
            "subject": "Matematika",
            "teacher": "Pak Guru A",
            "time_slot": "Jam Ke 1-3",
            "start_time": "07:00",
            "end_time": "09:30"
        }
    ]
}
```

---

## 10. Data Master

### Get Teachers List
**Endpoint**: `GET /api/teachers`  
**Query Params**: `?search=guru` (optional)  
**Header**: `Authorization: Bearer <token>`

**Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Pak Guru A",
            "nip": "19800101...",
            "code": "G001",
            "subject_name": "Matematika",
            "photo_url": "https://..."
        }
    ]
}
```

---


## Catatan Implementasi Backend

> [!NOTE]
> **Status Update (2026-02-05)**: ✅ **SEMUA ENDPOINT SUDAH DIIMPLEMENTASIKAN!**
> 
> Semua endpoint yang dibutuhkan mobile app sudah tersedia dan 100% compatible dengan `ApiService.kt`.
> Lihat: `/root/.gemini/antigravity/brain/[conversation-id]/implementation_summary.md`

### 1. **Endpoint Baru yang Sudah Dibuat**: ✅
- ✅ `GET /api/me/dashboard/summary` (Siswa) - **IMPLEMENTED** (2026-02-05)
- ✅ `GET /api/me/dashboard/teacher-summary` (Guru) - **IMPLEMENTED** (2026-02-05)
- ✅ `GET /api/me/homeroom/dashboard` (Wali Kelas) - **IMPLEMENTED** (2026-02-05)
- ✅ `GET /api/me/students/follow-up` (Guru - Tindak Lanjut) - **IMPLEMENTED** (2026-02-05)
- ✅ `GET /api/me/notifications` (Guru/Siswa) - **IMPLEMENTED** (alias ke `/mobile/notifications`)
- ✅ `GET /api/teachers` - **ACCESSIBLE** untuk student & teacher (2026-02-05)
- ✅ `GET /api/me/homeroom/attendance` (Wali Kelas - Riwayat) - **IMPLEMENTED** (2026-02-07)
- ✅ `GET /api/me/schedules` (Siswa - Jadwal) - **IMPLEMENTED** (2026-02-07)
- ✅ `GET /api/classes` (List Kelas) - **IMPLEMENTED** (2026-02-07)

**Controllers**:
- `DashboardController@studentDashboard` - Student dashboard dengan jadwal + status kehadiran
- `DashboardController@teacherDashboard` - Teacher dashboard dengan jadwal + attendance summary
- `DashboardController@homeroomDashboard` - Homeroom dashboard dengan class info + attendance
- `TeacherController@getStudentsFollowUp` - Daftar siswa yang perlu tindak lanjut
- `MobileNotificationController@index` - Notifikasi (sudah ada, ditambahkan alias)

### 2. **Endpoint yang Sudah Ada**: ✅
- ✅ `GET /api/me/attendance/teaching` - **SUDAH ADA** (supports `?date=` and `?status=`)
- ✅ `GET /api/me/attendance` - **SUDAH ADA** (supports `?month=` and `?year=`)
- ✅ `GET /api/teachers` - **SUDAH ADA** (field `code` available via TeacherResource)
- ✅ `POST /api/attendance/scan` - **SUDAH ADA**
- ✅ `GET /api/me/schedules` - **SUDAH ADA** (untuk siswa)

### 3. **Response Format**: ✅
- ✅ Tanggal: format `YYYY-MM-DD` (compatible dengan mobile)
- ✅ Waktu: format `HH:mm` (24 jam)
- ✅ Timezone: `Asia/Jakarta` (WIB) via `now()->locale('id')`
- ✅ **Status enum**: `present`, `late`, `excused`, `sick`, `absent`, `dinas`, `izin`
- ✅ Status labels dalam bahasa Indonesia

### 4. **Authentication**: ✅
- ✅ Login response returns `role` (`student`, `teacher`, `admin`)
- ✅ Untuk siswa, returns `is_class_officer` (boolean)
- ✅ Token Sanctum/JWT ready

### 5. **Compatibility**: ✅
- ✅ All endpoint paths match `ApiService.kt`
- ✅ All response structures compatible
- ✅ No breaking changes to web/desktop
- ✅ Mobile app ready to connect!

---

## 11. Statistik Guru

### Get Monthly Statistics
**Endpoint**: `GET /api/me/statistics/monthly`
**Header**: `Authorization: Bearer <token>`
**Query Params**: `?month=2&year=2025`

**Response (200 OK)**:
```json
{
    "month": "2",
    "year": "2025",
    "summary": {
        "hadir": 20,
        "sakit": 1,
        "izin": 0,
        "alfa": 0
    },
    "chart_data": [
        { "day": 1, "status": "hadir" },
        { "day": 2, "status": "hadir" }
    ]
}
```

---



## 🚀 Status: READY FOR MOBILE APP INTEGRATION

Mobile app sekarang bisa langsung connect ke backend dengan update `BASE_URL` di `ApiClient.kt`.
