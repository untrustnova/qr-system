# Changes

## [2026-02-06] - High-Performance, Security & Cross-Platform Update
### Added
- **Performance:** Integrated **Laravel Octane** with FrankenPHP support.
- **Queue Management:** Integrated **Laravel Horizon** for Redis queue monitoring (Admin only).
- **Feature Flags:** Integrated **Laravel Pennant** for gradual feature rollouts (e.g., `new-mobile-dashboard`).
- **Interactive CLI:** Added `php artisan app:check` using **Laravel Prompts** for interactive system management.
- **Cross-Platform Setup:** Added `setup.sh` (Bash) and `setup.ps1` (PowerShell) for automated environment preparation.
- **WhatsApp Integration:** Added queued event listener for real-time WhatsApp attendance notifications.
- **Teacher Self-Service:** Teachers can now upload their own schedule images.
- **Status Support:** Added `pulang` status to all attendance and reporting modules.
- **Security (Hardened Storage):** Migrated all sensitive uploads (Teacher/Class schedules, Attendance attachments) to the **Private Storage** (`local` disk).
- **Security (Authorized Streaming):** Implemented secure, role-validated streaming for all files, disabling direct public URL access to storage.
- **Testing (Pest):** Implemented a comprehensive test suite covering:
    *   Authentication & RBAC Roles.
    *   Interactive Dashboards (Admin, Student, Teacher, Homeroom).
    *   Secure File Downloads & Authorization.
    *   Mobile Notification Generation.
    *   Teacher Absence Reporting logic.
- **CI/CD:** Enhanced GitHub Actions with Pint linting and Redis-backed parallel testing.
- **Docker Optimization:** Completely overhauled `Dockerfile` and `compose.yaml`:
    *   Switched to **Alpine-based** images for significantly smaller disk footprint.
    *   Implemented **RAM limits** for all services (App, DB, Redis, Vite) to prevent system resource exhaustion.
    *   Added a dedicated **Redis** service to the compose stack for native cache/queue support.
    *   Enabled **OPcache** and optimized PHP extensions for production-grade performance.

### Changed
- **Architecture:** Switched default `CACHE_STORE` and `QUEUE_CONNECTION` to Redis.
- **Permissions:** Students can now view their class schedules via `/api/schedules`.
- **Validation:** Teachers are now strictly restricted to scanning student QRs only for their assigned schedules.
- **Dashboard:** Improved student/teacher dashboards with proper day-name filtering.
- **README:** Completely overhauled documentation to reflect the new architecture.

### Fixed
- **Installation:** Resolved "missing database" crashes during initial `composer install` by adding boot safeguards in `AppServiceProvider`.
- **Laravel Boost:** Fixed blocking errors during `composer update` by making the boost command optional (`|| true`).
- **Stability:** Fixed inconsistent day-name vs day-integer filtering in dashboard queries.
- **Code Quality:** Systematic cleanup of FQCNs (Fully Qualified Class Names), replacing inline classes with imported `use` statements.

---

## [Unreleased] - Previous Updates
- Added majors (jurusan) master data and class linkage.
- Added absence request workflow (dispensation/sick/permit) with Waka approval and signature.
- Added class-officer role flag for students and QR validation rules.
- Added bulk schedule management per day with subject_name support and broadcasts.
- Added Scalar API docs at `/docs` and OpenAPI JSON.
- Added web login for schedules and role-restricted web view.
- Added activity logging middleware and controller logs.
- Expanded OpenAPI spec coverage and added auth sample payloads.