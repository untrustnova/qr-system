# QR Attendance API (Laravel 12 + FrankenPHP/Octane/Redis)

API-only QR-based attendance system for schools with multiple roles: Admin, Waka (Student Affairs), Teachers, and Students. Highly optimized for performance and real-time interactions.

## 🚀 Optimized Stack
- **Engine:** [Laravel Octane](https://laravel.com/docs/12.x/octane) with **FrankenPHP** for high-concurrency.
- **Real-time:** [Laravel Reverb](https://reverb.laravel.com/) for WebSocket broadcasting.
- **Cache & Queue:** [Redis](https://redis.io/) via `predis`.
- **Monitoring:** [Laravel Horizon](https://laravel.com/docs/12.x/horizon) for queues & [Laravel Telescope](https://laravel.com/docs/12.x/telescope) for debugging.
- **Security:** Laravel Sanctum (Tokens), Device Binding, and Role-Based Access Control (RBAC).

## 🛠️ One-Command Setup
After cloning, you can set up the entire environment (including DB, dependencies, assets, and keys) with a single command:

**Linux / macOS:**
```bash
chmod +x setup.sh
./setup.sh
```

**Windows (PowerShell):**
```powershell
.\setup.ps1
```

## 🌐 Dashboard Access
- **API Documentation (Scalar):** `http://localhost:8000/docs`
- **Queue Monitor (Horizon):** `http://localhost:8000/horizon` (Admin only)
- **Debug Tool (Telescope):** `http://localhost:8000/telescope` (Admin only)
- **Web Login:** `http://localhost:8000/login`

## 🐳 Docker (Sail) Deployment
The project is optimized for Docker with low-resource Alpine images and memory limits:
```bash
# Start the stack
./vendor/bin/sail up -d

# Initial setup inside Docker
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan horizon:publish
```

## 💻 CLI Manager
The application includes a professional interactive manager for maintenance:
```bash
php artisan app:check
```
Use this to check system health, view statistics (Total Students/Teachers/Classes), clear cache, and manage feature flags interactively.

## 📱 Mobile & Webta Compatibility
The API is 100% compatible with the Mobile and Webta frontend requirements:
- **Status Support:** `present`, `late`, `excused`, `sick`, `absent`, `dinas`, `izin`, and **`pulang`**.
- **Special Aliases:** Supports `PATCH` for attendance updates and `POST /document` for attachments.
- **Class Officer Flow:** Class officers can generate QR tokens for their class using `POST /api/me/class/qr-token`.
- **Teacher Verification:** Teachers scan student-held QRs to verify their presence in class.

## 🤖 Automated Tasks (Scheduler)
- **QR Cleanup:** Expired QR codes are deactivated every minute.
- **Auto-Alpha:** Students who don't scan for their scheduled classes are automatically marked as "Absent" at 16:00 daily.
- **WhatsApp Alerts:** Instant notifications sent to students/teachers via WhatsApp when attendance is recorded.

## 🧪 Testing & CI/CD
- **Local:** `php artisan test` (supports parallel testing).
- **CI/CD:** Automated GitHub Actions pipeline (`.github/workflows/ci.yml`) handles linting (Pint) and feature testing with Redis.

---
© 2026 QR Absence System