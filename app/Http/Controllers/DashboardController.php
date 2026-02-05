<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Major;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function adminSummary(): JsonResponse
    {
        $stats = Cache::remember('admin_summary_stats', 600, function () {
            return [
                'students_count' => StudentProfile::count(),
                'teachers_count' => TeacherProfile::count(),
                'classes_count' => Classes::count(),
                'majors_count' => Major::count(),
                'rooms_count' => Room::count(),
            ];
        });

        return response()->json($stats);
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
            ->where('day', now()->format('l'))
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
            ->where('day', now()->format('l'))
            ->with('class')
            ->orderBy('start_time')
            ->get();

        $scheduleToday = $schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'subject' => $schedule->subject_name,
                'class_name' => $schedule->class?->name ?? 'N/A',
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
            ->where('day', now()->format('l'))
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
