<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Major;
use App\Models\Schedule;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function adminSummary(): JsonResponse
    {
        $today = now()->format('Y-m-d');
        
        // Get attendance stats for today
        $attendanceStats = Attendance::whereDate('date', $today)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return response()->json([
            'students_count' => StudentProfile::count(),
            'teachers_count' => TeacherProfile::count(),
            'classes_count' => Classes::count(),
            'majors_count' => Major::count(),
            'attendance_today' => [
                'hadir' => $attendanceStats->get('present', 0),
                'izin' => $attendanceStats->get('izin', 0) + $attendanceStats->get('excused', 0),
                'sakit' => $attendanceStats->get('sick', 0),
                'alpha' => $attendanceStats->get('absent', 0),
                'terlambat' => $attendanceStats->get('late', 0),
                'pulang' => 0, // Placeholder
            ]
        ]);
    }

    public function attendanceSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = Attendance::query();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $summary = $query->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return response()->json($summary);
    }

    /**
     * Get student dashboard summary (Mobile App)
     * Returns today's schedule with attendance status
     */
    public function studentDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->studentProfile()->with('classRoom')->first();

        if (! $student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $today = now()->format('Y-m-d');
        $dayName = now()->locale('id')->translatedFormat('l');

        // Get today's schedules for student's class
        $schedules = Schedule::where('class_id', $student->class_id)
            ->where('day', now()->dayOfWeek)
            ->with(['teacher.user'])
            ->orderBy('start_time')
            ->get();

        $scheduleToday = $schedules->map(function ($schedule) use ($student, $today) {
            // Get attendance for this schedule today
            $attendance = Attendance::where('student_id', $student->id)
                ->where('schedule_id', $schedule->id)
                ->whereDate('date', $today)
                ->first();

            return [
                'id' => $schedule->id,
                'time_slot' => $schedule->title ?? 'Jam Ke '.$schedule->id,
                'subject' => $schedule->subject_name,
                'teacher' => $schedule->teacher?->user?->name ?? 'N/A',
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
                'status' => $attendance?->status ?? 'none',
                'status_label' => $this->getStatusLabel($attendance?->status),
                'check_in_time' => $attendance?->checked_in_at?->format('H:i'),
            ];
        });

        return response()->json([
            'date' => $today,
            'day_name' => $dayName,
            'student' => [
                'name' => $user->name,
                'class_name' => $student->classRoom?->name ?? 'N/A',
                'nis' => $student->nis,
                'photo_url' => null,
                'is_class_officer' => $student->is_class_officer,
            ],
            'school_hours' => [
                'start_time' => '07:00',
                'end_time' => '15:00',
            ],
            'schedule_today' => $scheduleToday,
        ]);
    }

    /**
     * Get teacher dashboard summary (Mobile App)
     * Returns today's teaching schedule and attendance summary
     */
    public function teacherDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacherProfile;

        if (! $teacher) {
            return response()->json(['message' => 'Teacher profile not found'], 404);
        }

        $today = now()->format('Y-m-d');
        $dayName = now()->locale('id')->translatedFormat('l');

        // Get today's teaching schedules
        $schedules = Schedule::where('teacher_id', $teacher->id)
            ->where('day', now()->dayOfWeek)
            ->with('class')
            ->orderBy('start_time')
            ->get();

        $scheduleToday = $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'subject' => $schedule->subject_name,
                'class_name' => $schedule->class?->name ?? 'N/A',
                'class_id' => $schedule->class_id, // Added class_id
                'time_slot' => $schedule->title ?? 'Jam Ke '.$schedule->id,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
            ];
        });

        // Get attendance summary for all students taught by this teacher (today)
        $scheduleIds = $schedules->pluck('id');
        $attendanceSummary = Attendance::whereIn('schedule_id', $scheduleIds)
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return response()->json([
            'date' => $today,
            'day_name' => $dayName,
            'teacher' => [
                'name' => $user->name,
                'nip' => $teacher->nip,
                'code' => $teacher->nip,
                'photo_url' => null,
            ],
            'school_hours' => [
                'start_time' => '07:00',
                'end_time' => '15:00',
            ],
            'attendance_summary' => [
                'present' => $attendanceSummary->get('present', 0),
                'sick' => $attendanceSummary->get('sick', 0),
                'excused' => $attendanceSummary->get('excused', 0) + $attendanceSummary->get('izin', 0),
                'absent' => $attendanceSummary->get('absent', 0),
            ],
            'schedule_today' => $scheduleToday,
        ]);
    }

    /**
     * Get homeroom teacher dashboard (Mobile App)
     * Returns homeroom class info, attendance summary, and today's schedule
     */
    public function homeroomDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacherProfile()->with('homeroomClass')->first();

        if (! $teacher || ! $teacher->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom class not found'], 404);
        }

        $today = now()->format('Y-m-d');
        $homeroomClass = $teacher->homeroomClass;

        // Get today's schedules for homeroom class
        $schedules = Schedule::where('class_id', $homeroomClass->id)
            ->where('day', now()->dayOfWeek)
            ->with(['teacher.user'])
            ->orderBy('start_time')
            ->get();

        $scheduleToday = $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'subject' => $schedule->subject_name,
                'teacher' => $schedule->teacher?->user?->name ?? 'N/A',
                'time_slot' => $schedule->title ?? 'Jam Ke '.$schedule->id,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
            ];
        });

        // Get attendance summary for homeroom class today
        $scheduleIds = $schedules->pluck('id');
        $attendanceSummary = Attendance::whereIn('schedule_id', $scheduleIds)
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $totalStudents = StudentProfile::where('class_id', $homeroomClass->id)->count();

        return response()->json([
            'date' => $today,
            'homeroom_class' => [
                'id' => $homeroomClass->id,
                'name' => $homeroomClass->name,
                'total_students' => $totalStudents,
            ],
            'attendance_summary' => [
                'present' => $attendanceSummary->get('present', 0),
                'late' => $attendanceSummary->get('late', 0),
                'sick' => $attendanceSummary->get('sick', 0),
                'excused' => $attendanceSummary->get('excused', 0) + $attendanceSummary->get('izin', 0),
                'absent' => $attendanceSummary->get('absent', 0),
            ],
            'schedule_today' => $scheduleToday,
        ]);
    }

    /**
     * Get Waka (Vice Principal) dashboard summary
     * Returns today's stats and monthly trend
     */
    public function wakaDashboard(Request $request): JsonResponse
    {
        $today = now()->format('Y-m-d');
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d');

        // 1. Stats Hari Ini (Today's Stats)
        $todayStats = Attendance::whereDate('date', $today)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $statistik = [
            'hadir' => $todayStats->get('present', 0),
            'izin' => $todayStats->get('izin', 0) + $todayStats->get('excused', 0),
            'sakit' => $todayStats->get('sick', 0),
            'alpha' => $todayStats->get('absent', 0),
            'terlambat' => $todayStats->get('late', 0),
            'pulang' => 0, // Logic for 'pulang' might need specific definition, set 0 for now
        ];

        // 2. Tren Bulanan (Monthly Trend)
        // Get daily counts for the current month
        $monthlyData = Attendance::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('DATE(date) as date, status, count(*) as count')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy('date');

        // Fill in missing days
        $trend = [];
        $currentDate = now()->startOfMonth();
        while ($currentDate <= now()->endOfMonth()) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayData = $monthlyData->get($dateStr, collect([]));

            $hadir = $dayData->where('status', 'present')->sum('count');
            $izin = $dayData->whereIn('status', ['izin', 'excused'])->sum('count');
            $sakit = $dayData->where('status', 'sick')->sum('count');
            $alpha = $dayData->where('status', 'absent')->sum('count');
            $terlambat = $dayData->where('status', 'late')->sum('count');

            $trend[] = [
                'date' => $dateStr,
                'label' => $currentDate->format('d M'), // e.g., "01 Feb"
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpha' => $alpha,
                'terlambat' => $terlambat,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'date' => $today,
            'statistik' => $statistik,
            'trend' => $trend,
        ]);
    }

    /**
     * Helper method to get status label in Indonesian
     */
    private function getStatusLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'Hadir Tepat Waktu',
            'late' => 'Hadir Terlambat',
            'sick' => 'Sakit',
            'excused', 'izin' => 'Izin',
            'absent' => 'Alpha',
            'dinas' => 'Dinas',
            default => 'Belum Absen',
        };
    }
}
