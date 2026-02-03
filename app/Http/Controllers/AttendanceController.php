<?php

namespace App\Http\Controllers;

use App\Events\AttendanceRecorded;
use App\Models\Attendance;
use App\Models\AttendanceAttachment;
use App\Models\Classes;
use App\Models\Qrcode;
use App\Models\Schedule;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'exists:qrcodes,token'],
            'device_id' => ['nullable', 'integer'],
        ]);

        $qr = Qrcode::with('schedule')->where('token', $data['token'])->firstOrFail();

        if (!$qr->is_active || $qr->isExpired()) {
            return response()->json(['message' => 'QR tidak aktif atau sudah kadaluarsa'], 422);
        }

        $user = $request->user();
        $now = now();

        if ($qr->type === 'student' && $user->user_type !== 'student') {
            return response()->json(['message' => 'QR hanya untuk siswa'], 403);
        }

        if ($qr->type === 'teacher' && $user->user_type !== 'teacher') {
            return response()->json(['message' => 'QR hanya untuk guru'], 403);
        }

        if ($user->user_type === 'student' && !$user->studentProfile) {
            return response()->json(['message' => 'Profil siswa tidak ditemukan'], 422);
        }

        if ($user->user_type === 'teacher' && !$user->teacherProfile) {
            return response()->json(['message' => 'Profil guru tidak ditemukan'], 422);
        }

        if ($user->user_type === 'student') {
            if (!$data['device_id'] ?? null) {
                return response()->json(['message' => 'Device belum terdaftar'], 422);
            }

            $device = $user->devices()->where('id', $data['device_id'])->where('active', true)->first();
            if (!$device) {
                return response()->json(['message' => 'Device tidak valid'], 422);
            }

            $device->update(['last_used_at' => $now]);
        }

        if ($qr->type === 'student' && $qr->schedule && $user->studentProfile && $qr->schedule->class_id !== $user->studentProfile->class_id) {
            return response()->json(['message' => 'QR bukan untuk kelas kamu'], 403);
        }

        if ($qr->type === 'teacher' && $qr->schedule && $qr->schedule->teacher_id !== optional($user->teacherProfile)->id) {
            return response()->json(['message' => 'QR bukan untuk guru ini'], 403);
        }

        $attributes = [
            'attendee_type' => $user->user_type,
            'schedule_id' => $qr->schedule_id,
            'student_id' => $user->studentProfile->id ?? null,
            'teacher_id' => $user->teacherProfile->id ?? null,
        ];

        $existing = Attendance::where($attributes)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Presensi sudah tercatat',
                'attendance' => $existing->load(['student.user', 'teacher.user', 'schedule']),
            ]);
        }

        $attendance = Attendance::create([
            ...$attributes,
            'date' => $now,
            'qrcode_id' => $qr->id,
            'status' => 'present',
            'checked_in_at' => $now,
            'source' => 'qrcode',
        ]);

        // dispatch event after creation to ensure ID is available
        AttendanceRecorded::dispatch($attendance); 

        Log::info('attendance.recorded', [
            'attendance_id' => $attendance->id,
            'schedule_id' => $attendance->schedule_id,
            'user_id' => $user->id,
            'attendee_type' => $attendance->attendee_type,
        ]);

        return response()->json($attendance->load(['student.user', 'teacher.user', 'schedule']));
    }

    public function me(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'student' || !$request->user()->studentProfile) {
            abort(403, 'Hanya untuk siswa');
        }

        $query = Attendance::query()
            ->with(['schedule.teacher.user', 'schedule.class'])
            ->where('student_id', $request->user()->studentProfile->id);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $request->validate([
                'status' => ['in:present,late,excused,sick,absent,dinas,izin'],
            ]);
            $query->where('status', $request->string('status'));
        }

        $attendances = $query->latest('date')->paginate();

        return response()->json($attendances);
    }

    public function summaryMe(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'student' || !$request->user()->studentProfile) {
            abort(403, 'Hanya untuk siswa');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $studentId = $request->user()->studentProfile->id;

        $baseQuery = Attendance::query()->where('student_id', $studentId);

        if ($request->filled('from')) {
            $baseQuery->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $baseQuery->whereDate('date', '<=', $request->date('to'));
        }

        $statusSummary = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        $dailySummary = (clone $baseQuery)
            ->selectRaw('DATE(date) as day, status, count(*) as total')
            ->groupBy('day', 'status')
            ->orderBy('day')
            ->get();

        return response()->json([
            'status_summary' => $statusSummary,
            'daily_summary' => $dailySummary,
        ]);
    }

    public function meTeaching(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:present,late,excused,sick,absent,dinas,izin'],
        ]);

        $teacherId = $request->user()->teacherProfile->id;

        $query = Attendance::query()
            ->with(['schedule.class', 'schedule.teacher.user'])
            ->where('attendee_type', 'teacher')
            ->where('teacher_id', $teacherId);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->latest('date')->paginate());
    }

    public function summaryTeaching(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $teacherId = $request->user()->teacherProfile->id;

        $query = Attendance::query()
            ->where('attendee_type', 'teacher')
            ->where('teacher_id', $teacherId);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $statusSummary = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'status_summary' => $statusSummary,
            'total_sessions' => $query->count(),
        ]);
    }

    public function studentsAttendanceSummary(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'threshold' => ['nullable', 'integer', 'min:1'],
        ]);

        $teacherId = $request->user()->teacherProfile->id;

        $query = Attendance::query()
            ->with(['student.user', 'schedule.class'])
            ->where('attendee_type', 'student')
            ->whereHas('schedule', function ($q) use ($teacherId): void {
                $q->where('teacher_id', $teacherId);
            });

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $perPage = $this->resolvePerPage($request);
        $studentIds = [];
        if ($perPage) {
            $studentIdsPage = (clone $query)
                ->select('student_id')
                ->distinct()
                ->orderBy('student_id')
                ->paginate($perPage);

            $studentIds = $studentIdsPage->getCollection()->pluck('student_id')->all();

            $raw = (clone $query)
                ->whereIn('student_id', $studentIds)
                ->selectRaw('student_id, status, count(*) as total')
                ->groupBy('student_id', 'status')
                ->get();
        } else {
            $raw = (clone $query)
                ->selectRaw('student_id, status, count(*) as total')
                ->groupBy('student_id', 'status')
                ->get();
        }

        $grouped = $raw->groupBy('student_id')->map(function ($rows): array {
            $totals = $rows->pluck('total', 'status')->all();
            return [
                'student_id' => $rows->first()->student_id,
                'totals' => $totals,
            ];
        })->values();

        if ($request->filled('threshold')) {
            $threshold = $request->integer('threshold');
            $grouped = $grouped->filter(function (array $item) use ($threshold): bool {
                foreach ($item['totals'] as $count) {
                    if ($count >= $threshold) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        if (!$perPage) {
            $studentIds = $grouped->pluck('student_id')->all();
        }

        $students = StudentProfile::query()
            ->with('user')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $response = $grouped->map(function (array $item) use ($students): array {
            return [
                'student' => $students->get($item['student_id']),
                'totals' => $item['totals'],
            ];
        });

        if ($perPage) {
            $studentIdsPage->setCollection($response->values());
            return response()->json($studentIdsPage);
        }

        return response()->json($response);
    }

    public function classAttendanceByDate(Request $request, Classes $class): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        if (optional($class->homeroomTeacher)->id !== $request->user()->teacherProfile->id) {
            abort(403, 'Hanya wali kelas yang boleh melihat data ini');
        }

        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->string('date'));
        $day = $date->format('l');

        $schedules = Schedule::query()
            ->with(['teacher.user', 'class'])
            ->where('class_id', $class->id)
            ->where('day', $day)
            ->orderBy('start_time')
            ->get();

        $scheduleIds = $schedules->pluck('id')->all();

        $attendances = Attendance::query()
            ->with(['student.user', 'schedule'])
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('date', $date->toDateString())
            ->get()
            ->groupBy('schedule_id');

        $items = $schedules->map(function (Schedule $schedule) use ($attendances): array {
            return [
                'schedule' => $schedule,
                'attendances' => $attendances->get($schedule->id, collect())->values(),
            ];
        });

        return response()->json([
            'class' => $class,
            'date' => $date->toDateString(),
            'day' => $day,
            'items' => $items,
        ]);
    }

    public function classStudentsSummary(Request $request, Classes $class): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        if (optional($class->homeroomTeacher)->id !== $request->user()->teacherProfile->id) {
            abort(403, 'Hanya wali kelas yang boleh melihat data ini');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'threshold' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Attendance::query()
            ->where('attendee_type', 'student')
            ->whereHas('schedule', function ($q) use ($class): void {
                $q->where('class_id', $class->id);
            });

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $perPage = $this->resolvePerPage($request);
        $studentIds = [];
        if ($perPage) {
            $studentIdsPage = (clone $query)
                ->select('student_id')
                ->distinct()
                ->orderBy('student_id')
                ->paginate($perPage);

            $studentIds = $studentIdsPage->getCollection()->pluck('student_id')->all();

            $raw = (clone $query)
                ->whereIn('student_id', $studentIds)
                ->selectRaw('student_id, status, count(*) as total')
                ->groupBy('student_id', 'status')
                ->get();
        } else {
            $raw = (clone $query)
                ->selectRaw('student_id, status, count(*) as total')
                ->groupBy('student_id', 'status')
                ->get();
        }

        $grouped = $raw->groupBy('student_id')->map(function ($rows): array {
            return [
                'student_id' => $rows->first()->student_id,
                'totals' => $rows->pluck('total', 'status')->all(),
            ];
        })->values();

        if ($request->filled('threshold')) {
            $threshold = $request->integer('threshold');
            $grouped = $grouped->filter(function (array $item) use ($threshold): bool {
                foreach ($item['totals'] as $count) {
                    if ($count >= $threshold) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        if (!$perPage) {
            $studentIds = $grouped->pluck('student_id')->all();
        }

        $students = $class->students()
            ->with('user')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $response = $grouped->map(function (array $item) use ($students): array {
            return [
                'student' => $students->get($item['student_id']),
                'totals' => $item['totals'],
            ];
        });

        if ($perPage) {
            $studentIdsPage->setCollection($response->values());
            return response()->json($studentIdsPage);
        }

        return response()->json($response);
    }

    public function classStudentsAbsences(Request $request, Classes $class): JsonResponse
    {
        if ($request->user()->user_type !== 'teacher' || !$request->user()->teacherProfile) {
            abort(403, 'Hanya untuk guru');
        }

        if (optional($class->homeroomTeacher)->id !== $request->user()->teacherProfile->id) {
            abort(403, 'Hanya wali kelas yang boleh melihat data ini');
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:present,late,excused,sick,absent,dinas,izin'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'schedule'])
            ->where('attendee_type', 'student')
            ->whereHas('schedule', function ($q) use ($class): void {
                $q->where('class_id', $class->id);
            });

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->where('status', '!=', 'present');
        }

        $perPage = $this->resolvePerPage($request);
        if ($perPage) {
            $studentIdsPage = (clone $query)
                ->select('student_id')
                ->distinct()
                ->orderBy('student_id')
                ->paginate($perPage);

            $studentIds = $studentIdsPage->getCollection()->pluck('student_id')->all();

            $items = $query
                ->whereIn('student_id', $studentIds)
                ->orderBy('date')
                ->get()
                ->groupBy('student_id');

            $response = collect($studentIds)->map(function ($studentId) use ($items): array {
                $rows = $items->get($studentId, collect());
                $student = optional($rows->first())->student;

                return [
                    'student' => $student ? $student->loadMissing('user') : null,
                    'items' => $rows->values(),
                ];
            });

            $studentIdsPage->setCollection($response);
            return response()->json($studentIdsPage);
        }

        $perPage = $this->resolvePerPage($request);
        if ($perPage) {
            $studentIdsPage = (clone $query)
                ->select('student_id')
                ->distinct()
                ->orderBy('student_id')
                ->paginate($perPage);

            $studentIds = $studentIdsPage->getCollection()->pluck('student_id')->all();

            $items = $query
                ->whereIn('student_id', $studentIds)
                ->orderBy('date')
                ->get()
                ->groupBy('student_id');

            $response = collect($studentIds)->map(function ($studentId) use ($items): array {
                $rows = $items->get($studentId, collect());
                $student = optional($rows->first())->student;

                return [
                    'student' => $student ? $student->loadMissing('user') : null,
                    'items' => $rows->values(),
                ];
            });

            $studentIdsPage->setCollection($response);
            return response()->json($studentIdsPage);
        }

        $items = $query->orderBy('date')->get()->groupBy('student_id');

        $response = $items->map(function ($rows): array {
            $student = optional($rows->first())->student;

            return [
                'student' => $student ? $student->loadMissing('user') : null,
                'items' => $rows->values(),
            ];
        })->values();

        return response()->json($response);
    }

    public function teachersDailyAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->string('date'))->toDateString();

        $perPage = $this->resolvePerPage($request);
        $teachersQuery = TeacherProfile::query()
            ->with('user')
            ->orderBy('id');

        $teachers = $perPage
            ? $teachersQuery->paginate($perPage)
            : $teachersQuery->get();

        $teacherIds = $perPage
            ? $teachers->getCollection()->pluck('id')->all()
            : $teachers->pluck('id')->all();

        $attendanceByTeacher = Attendance::query()
            ->where('attendee_type', 'teacher')
            ->whereDate('date', $date)
            ->whereIn('teacher_id', $teacherIds)
            ->orderByDesc('checked_in_at')
            ->get()
            ->groupBy('teacher_id');

        $items = ($perPage ? $teachers->getCollection() : $teachers)->map(function (TeacherProfile $teacher) use ($attendanceByTeacher): array {
            $attendance = $attendanceByTeacher->get($teacher->id)?->first();

            return [
                'teacher' => $teacher,
                'attendance' => $attendance,
                'status' => $attendance?->status ?? 'absent',
            ];
        });

        if ($perPage) {
            $teachers->setCollection($items);
            return response()->json([
                'date' => $date,
                'items' => $teachers,
            ]);
        }

        return response()->json([
            'date' => $date,
            'items' => $items,
        ]);
    }

    public function manual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attendee_type' => ['required', 'in:student,teacher'],
            'student_id' => ['nullable', 'exists:student_profiles,id'],
            'teacher_id' => ['nullable', 'exists:teacher_profiles,id'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'status' => ['required', 'in:present,late,excused,sick,absent,dinas,izin'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ]);

        if ($data['attendee_type'] === 'student' && empty($data['student_id'])) {
            abort(422, 'student_id wajib untuk attendee_type student');
        }

        if ($data['attendee_type'] === 'teacher' && empty($data['teacher_id'])) {
            abort(422, 'teacher_id wajib untuk attendee_type teacher');
        }

        $attributes = [
            'attendee_type' => $data['attendee_type'],
            'schedule_id' => $data['schedule_id'],
            'student_id' => $data['student_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
        ];

        $existing = Attendance::where($attributes)->first();

        if ($existing) {
            $existing->update([
                'status' => $data['status'],
                'reason' => $data['reason'] ?? null,
                'date' => $data['date'],
                'checked_in_at' => $existing->checked_in_at ?? $data['date'],
                'source' => 'manual',
            ]);

            return response()->json($existing->load(['student.user', 'teacher.user', 'schedule']));
        }

        $attendance = Attendance::create([
            ...$attributes,
            'date' => $data['date'],
            'status' => $data['status'],
            'reason' => $data['reason'] ?? null,
            'checked_in_at' => $data['date'],
            'source' => 'manual',
        ]);

        return response()->json($attendance->load(['student.user', 'teacher.user', 'schedule']), 201);
    }

    public function wakaSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Attendance::query()->where('attendee_type', 'student');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $statusSummary = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        $classSummary = (clone $query)
            ->selectRaw('schedules.class_id as class_id, status, count(*) as total')
            ->join('schedules', 'attendances.schedule_id', '=', 'schedules.id')
            ->groupBy('schedules.class_id', 'status')
            ->get()
            ->groupBy('class_id')
            ->map(function ($rows) {
                return $rows->pluck('total', 'status')->all();
            });

        $studentSummary = (clone $query)
            ->selectRaw('student_id, status, count(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(function ($rows) {
                return $rows->pluck('total', 'status')->all();
            });

        return response()->json([
            'status_summary' => $statusSummary,
            'class_summary' => $classSummary,
            'student_summary' => $studentSummary,
        ]);
    }

    public function studentsAbsences(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:present,late,excused,sick,absent,dinas,izin'],
            'class_id' => ['nullable', 'exists:classes,id'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'schedule.class'])
            ->where('attendee_type', 'student');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->where('status', '!=', 'present');
        }

        if ($request->filled('class_id')) {
            $classId = $request->integer('class_id');
            $query->whereHas('schedule', function ($q) use ($classId): void {
                $q->where('class_id', $classId);
            });
        }

        $items = $query->orderBy('date')->get()->groupBy('student_id');

        $response = $items->map(function ($rows): array {
            $student = optional($rows->first())->student;

            return [
                'student' => $student ? $student->loadMissing('user') : null,
                'items' => $rows->values(),
            ];
        })->values();

        return response()->json($response);
    }

    public function recap(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $summary = Attendance::selectRaw('attendee_type, status, count(*) as total')
            ->whereBetween('date', [$start, $end])
            ->groupBy('attendee_type', 'status')
            ->get();

        return response()->json($summary);
    }

    public function summaryBySchedule(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorizeSchedule($request, $schedule);

        $data = Attendance::selectRaw('status, count(*) as total')
            ->where('schedule_id', $schedule->id)
            ->groupBy('status')
            ->get();

        return response()->json($data);
    }

    public function summaryByClass(Request $request, \App\Models\Classes $class): JsonResponse
    {
        if ($request->user()->user_type === 'teacher') {
            $teacherId = optional($request->user()->teacherProfile)->id;
            $ownsSchedules = $class->schedules()->where('teacher_id', $teacherId)->exists();
            $isHomeroom = optional($class->homeroomTeacher)->id === $teacherId;
            if (!$ownsSchedules && !$isHomeroom) {
                abort(403, 'Tidak boleh melihat rekap kelas ini');
            }
        }

        $data = Attendance::selectRaw('status, count(*) as total')
            ->whereHas('schedule', fn ($q) => $q->where('class_id', $class->id))
            ->groupBy('status')
            ->get();

        return response()->json($data);
    }

    public function attach(Request $request, Attendance $attendance): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $this->storeAttachment($file);

        $attachment = $attendance->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'attachment' => $attachment,
            'url' => $this->signedUrl($attachment->path),
        ], 201);
    }

    protected function storeAttachment(UploadedFile $file): string
    {
        return $file->store('attendance-attachments');
    }

    public function void(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorizeSchedule($request, $attendance->schedule);

        $attendance->delete();

        return response()->json(['message' => 'Scan dibatalkan']);
    }

    protected function signedUrl(string $path): string
    {
        try {
            return Storage::temporaryUrl($path, now()->addMinutes(10));
        } catch (\Throwable $e) {
            return Storage::url($path);
        }
    }

    protected function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        if ($request->user()->user_type === 'teacher' && $schedule->teacher_id !== optional($request->user()->teacherProfile)->id) {
            abort(403, 'Tidak boleh mengakses jadwal ini');
        }
    }

    public function bySchedule(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->user_type === 'teacher' && $schedule->teacher_id !== optional($request->user()->teacherProfile)->id) {
            abort(403, 'Tidak boleh melihat presensi jadwal ini');
        }

        $query = Attendance::query()
            ->with(['student.user', 'teacher.user'])
            ->where('schedule_id', $schedule->id)
            ->latest('checked_in_at');

        $perPage = $this->resolvePerPage($request);
        $attendances = $perPage ? $query->paginate($perPage) : $query->get();

        return response()->json($attendances);
    }

    public function markExcuse(Request $request, Attendance $attendance): JsonResponse
    {
        if ($request->user()->user_type === 'teacher' && $attendance->schedule->teacher_id !== optional($request->user()->teacherProfile)->id) {
            abort(403, 'Tidak boleh mengubah presensi jadwal ini');
        }

        $data = $request->validate([
            'status' => ['required', 'in:present,late,excused,sick,absent,dinas,izin'],
            'reason' => ['nullable', 'string'],
        ]);

        $attendance->update([
            'status' => $data['status'],
            'reason' => $data['reason'] ?? null,
            'source' => 'manual',
        ]);

        return response()->json($attendance);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Attendance::with(['student.user', 'teacher.user', 'schedule.class']);

        if ($request->filled('schedule_id')) {
            $schedule = Schedule::findOrFail($request->integer('schedule_id'));
            if ($request->user()->user_type === 'teacher' && $schedule->teacher_id !== optional($request->user()->teacherProfile)->id) {
                abort(403, 'Tidak boleh mengekspor jadwal ini');
            }
            $query->where('schedule_id', $schedule->id);
        }

        if ($request->filled('class_id')) {
            $query->whereHas('schedule', fn ($q) => $q->where('class_id', $request->integer('class_id')));
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $attendances = $query->orderBy('checked_in_at')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_export.csv"',
        ];

        $callback = static function () use ($attendances): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Type', 'Name', 'Status', 'Checked In At', 'Reason', 'Class', 'Schedule']);

            foreach ($attendances as $attendance) {
                $name = $attendance->attendee_type === 'student'
                    ? optional($attendance->student?->user)->name
                    : optional($attendance->teacher?->user)->name;

                fputcsv($handle, [
                    $attendance->attendee_type,
                    $name,
                    $attendance->status,
                    optional($attendance->checked_in_at)->toDateTimeString(),
                    $attendance->reason,
                    optional($attendance->schedule?->class)->label,
                    optional($attendance->schedule)->title,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function resolvePerPage(Request $request): ?int
    {
        if (!$request->filled('per_page') && !$request->filled('page')) {
            return null;
        }

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = $request->integer('per_page', 15);

        return min(max($perPage, 1), 200);
    }
}
