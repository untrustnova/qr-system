# Sample Request Payloads
Use `{BASE_URL}` for your host (e.g., `http://localhost:8000`). All endpoints are under `/api/...`.

## Auth
`{BASE_URL}/api/auth/login`
```json
{
  "login": "username_or_email",
  "password": "secret"
}
```

`{BASE_URL}/api/auth/logout`
```json
{}
```

`{BASE_URL}/api/me`
```json
{}
```

---

## Devices (siswa)
`{BASE_URL}/api/me/devices`
```json
{
  "identifier": "device-uuid-or-hash",
  "name": "My Phone",
  "platform": "Android"
}
```

`{BASE_URL}/api/me/devices/{device_id}` (DELETE)
```json
{}
```

---

## Majors (Jurusan)
`{BASE_URL}/api/majors`
```json
{
  "code": "RPL",
  "name": "Rekayasa Perangkat Lunak",
  "category": "Teknologi Informasi"
}
```

---

## Classes
`{BASE_URL}/api/classes`
```json
{
  "grade": "10",
  "label": "10-A",
  "major_id": 1
}
```

---

## Teachers
`{BASE_URL}/api/teachers`
```json
{
  "name": "Guru 1",
  "username": "guru1",
  "email": "g1@example.com",
  "password": "secret123",
  "nip": "12345",
  "homeroom_class_id": 1,
  "subject": "Matematika",
  "phone": "62812xxxx",
  "contact": "Telegram/WA"
}
```

`{BASE_URL}/api/teachers/import`
```json
{
  "items": [
    {
      "name": "Guru 1",
      "username": "guru1",
      "email": "g1@example.com",
      "password": "secret123",
      "nip": "12345",
      "homeroom_class_id": 1,
      "subject": "Matematika",
      "phone": "62812xxxx",
      "contact": "Telegram/WA"
    }
  ]
}
```

---

## Students
`{BASE_URL}/api/students`
```json
{
  "name": "Siswa 1",
  "username": "siswa1",
  "email": "s1@example.com",
  "password": "secret123",
  "nisn": "99887766",
  "nis": "123456",
  "gender": "L",
  "address": "Alamat siswa",
  "class_id": 1,
  "is_class_officer": false,
  "phone": "62812xxxx",
  "contact": "Telegram/WA"
}
```

`{BASE_URL}/api/students/import`
```json
{
  "items": [
    {
      "name": "Siswa 1",
      "username": "siswa1",
      "email": "s1@example.com",
      "password": "secret123",
      "nisn": "99887766",
      "nis": "123456",
      "gender": "L",
      "address": "Alamat siswa",
      "class_id": 1,
      "is_class_officer": false,
      "phone": "62812xxxx",
      "contact": "Telegram/WA"
    }
  ]
}
```

---

## Schedules
`{BASE_URL}/api/schedules`
```json
{
  "day": "Monday",
  "start_time": "07:30",
  "end_time": "09:00",
  "title": "Matematika",
  "subject_name": "Matematika",
  "subject_id": 1,
  "teacher_id": 1,
  "class_id": 1,
  "room": "R-101",
  "semester": 1,
  "year": 2026
}
```

`{BASE_URL}/api/classes/{class_id}/schedules/bulk`
```json
{
  "day": "Senin",
  "semester": 1,
  "year": 2026,
  "items": [
    {
      "subject_name": "Matematika",
      "teacher_id": 1,
      "start_time": "07:00",
      "end_time": "09:40",
      "room": "R-101"
    }
  ]
}
```

`{BASE_URL}/api/schedules?date=2026-01-01&class_id=1`
```json
{}
```

---

## WhatsApp
`{BASE_URL}/api/wa/send-text`
```json
{
  "to": "6281234567890",
  "message": "Pesan WA"
}
```

`{BASE_URL}/api/wa/send-media`
```json
{
  "to": "6281234567890",
  "mediaBase64": "data:image/png;base64,iVBORw0KGgoAAA...",
  "filename": "bukti.png",
  "caption": "Bukti"
}
```

---

## Absence Requests (Dispensasi / Izin Sakit)
`{BASE_URL}/api/absence-requests`
```json
{
  "student_id": 12,
  "type": "sick",
  "start_date": "2026-01-10",
  "end_date": "2026-01-12",
  "reason": "Surat dokter"
}
```

`{BASE_URL}/api/absence-requests/{id}/approve`
```json
{
  "approver_signature": "Nama/Paraf"
}
```

---

## QR Codes
`{BASE_URL}/api/qrcodes/generate`
```json
{
  "schedule_id": 1,
  "type": "student",
  "expires_in_minutes": 15
}
```

`{BASE_URL}/api/qrcodes/{token}/revoke`
```json
{}
```

---

## Attendance
**Status valid:** `present, late, excused, sick, absent, dinas, izin`

`{BASE_URL}/api/attendance/scan`
```json
{
  "token": "qr-token",
  "device_id": 1
}
```

`{BASE_URL}/api/attendance/{attendance_id}/excuse`
```json
{
  "status": "excused",
  "reason": "Surat izin"
}
```

`{BASE_URL}/api/attendance/{attendance_id}/void`
```json
{}
```

`{BASE_URL}/api/attendance/{attendance_id}/attachments` (multipart/form-data)
```
file: <upload>
```

`{BASE_URL}/api/attendance/schedules/{schedule_id}?per_page=20&page=1`
```json
{}
```

`{BASE_URL}/api/attendance/export?class_id=1&from=2026-01-01&to=2026-01-31`
```json
{}
```

`{BASE_URL}/api/attendance/recap?month=2026-01`
```json
{}
```

`{BASE_URL}/api/attendance/schedules/{schedule_id}/summary`
```json
{}
```

`{BASE_URL}/api/attendance/classes/{class_id}/summary`
```json
{}
```

---

## Student view
`{BASE_URL}/api/me/attendance?from=2026-01-01&to=2026-01-31&status=late&per_page=20&page=1`
```json
{}
```

`{BASE_URL}/api/me/attendance/summary?from=2026-01-01&to=2026-01-31`
```json
{}
```

`{BASE_URL}/api/me/schedules?date=2026-01-31`
```json
{}
```

---

## Teacher view
`{BASE_URL}/api/me/attendance/teaching?from=2026-01-01&to=2026-01-31&status=present`
```json
{}
```

`{BASE_URL}/api/me/attendance/teaching/summary?from=2026-01-01&to=2026-01-31`
```json
{}
```

`{BASE_URL}/api/me/students/attendance-summary?from=2026-01-01&to=2026-01-31&threshold=3&per_page=20&page=1`
```json
{}
```

---

## Homeroom teacher (wali kelas)
`{BASE_URL}/api/classes/{class_id}/attendance?date=2026-01-31`
```json
{}
```

`{BASE_URL}/api/classes/{class_id}/students/attendance-summary?from=2026-01-01&to=2026-01-31&threshold=3&per_page=20&page=1`
```json
{}
```

`{BASE_URL}/api/classes/{class_id}/students/absences?from=2026-01-01&to=2026-01-31&per_page=20&page=1`
```json
{}
```

---

## Waka (admin type)
`{BASE_URL}/api/attendance/teachers/daily?date=2026-01-31&per_page=20&page=1`
```json
{}
```

`{BASE_URL}/api/attendance/manual`
```json
{
  "attendee_type": "student",
  "student_id": 12,
  "teacher_id": null,
  "schedule_id": 22,
  "status": "absent",
  "date": "2026-01-31",
  "reason": "Alpha"
}
```

`{BASE_URL}/api/waka/attendance/summary?from=2026-01-01&to=2026-01-31`
```json
{}
```

`{BASE_URL}/api/students/absences?from=2026-01-01&to=2026-01-31&class_id=1&per_page=20&page=1`
```json
{}
```

---

## Admin views
`{BASE_URL}/api/teachers/{teacher_id}/schedules?from=2026-01-01&to=2026-01-31&per_page=20&page=1`
```json
{}
```

`{BASE_URL}/api/classes/{class_id}/schedules?from=2026-01-01&to=2026-01-31&per_page=20&page=1`
```json
{}
```

---

## Optional Masters
`{BASE_URL}/api/school-years`
```json
{
  "name": "2026/2027",
  "start_year": 2026,
  "end_year": 2027,
  "active": true
}
```

`{BASE_URL}/api/semesters`
```json
{
  "name": "Semester 1",
  "school_year_id": 1,
  "active": true
}
```

`{BASE_URL}/api/rooms`
```json
{
  "name": "R-101",
  "location": "Lantai 1",
  "capacity": 30
}
```

`{BASE_URL}/api/subjects`
```json
{
  "code": "MAT",
  "name": "Matematika"
}
```

`{BASE_URL}/api/time-slots`
```json
{
  "name": "Sesi 1",
  "start_time": "07:30",
  "end_time": "09:00"
}
```
