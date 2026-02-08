<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    /**
     * Get notifications for mobile app (generated on-the-fly from attendance data)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->query('date', now()->format('Y-m-d'));
        
        $notifications = $this->generateNotifications($user, $date);
        
        return response()->json([
            'date' => $date,
            'notifications' => $notifications,
        ]);
    }
    
    /**
     * Generate notifications based on user role and attendance data
     */
    private function generateNotifications($user, $date): array
    {
        $notifications = [];
        
        if ($user->user_type === 'teacher') {
            $notifications = $this->generateTeacherNotifications($user, $date);
        } elseif ($user->user_type === 'student') {
            $notifications = $this->generateStudentNotifications($user, $date);
        } elseif ($user->user_type === 'admin' || $user->user_type === 'waka') { // Support admin/waka
             $notifications = $this->generateAdminNotifications($user, $date);
        }
        
        return $notifications;
    }

    /**
     * Generate notifications for Admin/Waka (All School Anomalies)
     */
    private function generateAdminNotifications($user, $date): array
    {
        $notifications = [];

        // Fetch all non-present attendances for today (Students)
        $anomalies = Attendance::whereDate('date', $date)
            ->where('attendee_type', 'student') // Only students for now
            ->whereIn('status', ['late', 'absent', 'sick', 'excused', 'izin'])
            ->with(['student.user', 'student.classRoom', 'schedule.subject'])
            ->latest()
            ->take(50) // Limit to 50 recent to avoid overload
            ->get();

        foreach ($anomalies as $attendance) {
            $studentName = $attendance->student?->user?->name ?? 'Siswa';
            $className = $attendance->student?->classRoom?->name ?? '-';
            $scheduleInfo = $attendance->schedule ? 
                ($attendance->schedule->subject?->name . ' (Jam ke-' . ($attendance->schedule->id) . ')') : 
                '';

            $type = match ($attendance->status) {
                'late' => 'terlambat',
                'sick' => 'sakit',
                'excused', 'izin' => 'rapor', // Mapping 'izin'/excused to 'rapor' (or allow new type if app supports) - App supports 'sakit', 'alpha', 'terlambat', 'rapor', 'tepat_waktu'
                'absent' => 'alpha',
                default => 'other',
            };
            
            // Fix type mapping to match Mobile App NotifikasiSemua.kt expectations
            // App handle: tepat_waktu, terlambat, alpha, sakit, rapor
            if ($attendance->status === 'excused' || $attendance->status === 'izin') {
                $type = 'rapor'; // Using 'rapor' as generic info/izin if 'izin' not explicitly supported in that specific `when` block? 
                // Wait, NotifikasiSemua.kt (Line 259) doesn't have "izin" case in `when(notif.tipe)`. 
                // It has `tepat_waktu`, `terlambat`, `alpha`, `sakit`, `rapor`.
                // So I map `izin` to `rapor` or maybe `sakit`? `rapor` seems ok as "Info".
            }

            $message = match ($attendance->status) {
                'late' => "{$studentName} terlambat",
                'sick' => "{$studentName} sakit",
                'excused', 'izin' => "{$studentName} izin",
                'absent' => "{$studentName} alpha",
                default => "{$studentName} status: {$attendance->status}",
            };

            $detail = "{$className}";
            if ($scheduleInfo) {
                $detail .= " - {$scheduleInfo}";
            }

            $notifications[] = [
                'id' => $attendance->id,
                'type' => $type,
                'message' => $message,
                'detail' => $detail, // Contains Class Name and Schedule info
                'time' => $attendance->created_at->format('H:i'),
                'created_at' => $attendance->created_at->toIso8601String(),
            ];
        }

        return $notifications;
    }
    
    /**
     * Generate notifications for teachers
     */
    private function generateTeacherNotifications($user, $date): array
    {
        $notifications = [];
        $teacherId = $user->teacherProfile?->id;
        
        if (! $teacherId) {
            return $notifications;
        }
        
        // Notifikasi kehadiran mengajar hari ini
        $teachingAttendances = Attendance::whereHas('schedule', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
            ->where('attendee_type', 'teacher')
            ->whereDate('date', $date)
            ->with('schedule.subject:id,name', 'schedule.class:id,name')
            ->get();
        
        foreach ($teachingAttendances as $attendance) {
            $type = match ($attendance->status) {
                'present' => 'tepat_waktu',
                'late' => 'terlambat',
                'dinas' => 'dinas',
                default => 'other',
            };
            
            $message = match ($attendance->status) {
                'present' => 'Anda mengajar tepat waktu pada',
                'late' => 'Anda terlambat mengajar pada',
                'dinas' => 'Anda sedang dinas pada',
                default => 'Status kehadiran',
            };
            
            $notifications[] = [
                'id' => $attendance->id,
                'type' => $type,
                'message' => $message,
                'detail' => sprintf(
                    'Pelajaran %s - %s',
                    $attendance->schedule->subject->name ?? 'Unknown',
                    $attendance->schedule->class->name ?? 'Unknown'
                ),
                'time' => $attendance->date->format('H:i'),
                'created_at' => $attendance->created_at->toIso8601String(),
            ];
        }
        
        // Cek siswa yang alpha hari ini
        $alphaCount = Attendance::whereHas('schedule', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
            ->where('attendee_type', 'student')
            ->where('status', 'absent')
            ->whereDate('date', $date)
            ->count();
        
        if ($alphaCount > 0) {
            $notifications[] = [
                'id' => 'alpha_'.now()->timestamp,
                'type' => 'alpha_siswa', // Note: Mobile might not handle this type in Admin view, but OK for Teacher
                'message' => "Ada {$alphaCount} siswa alpha hari ini",
                'detail' => 'Perlu tindak lanjut',
                'time' => now()->format('H:i'),
                'created_at' => now()->toIso8601String(),
            ];
        }
        
        // Cek siswa yang perlu tindak lanjut (alpha >= 3 dalam sebulan terakhir)
        $problematicStudents = Attendance::whereHas('schedule', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
            ->where('attendee_type', 'student')
            ->where('status', 'absent')
            ->whereDate('date', '>=', now()->subMonth())
            ->selectRaw('student_id, count(*) as alpha_count')
            ->groupBy('student_id')
            ->having('alpha_count', '>=', 3)
            ->count();
        
        if ($problematicStudents > 0) {
            $notifications[] = [
                'id' => 'followup_'.now()->timestamp,
                'type' => 'tindak_lanjut',
                'message' => "{$problematicStudents} siswa perlu tindak lanjut",
                'detail' => 'Sering alpha dalam sebulan terakhir',
                'time' => now()->format('H:i'),
                'created_at' => now()->toIso8601String(),
            ];
        }
        
        return $notifications;
    }
    
    /**
     * Generate notifications for students
     */
    private function generateStudentNotifications($user, $date): array
    {
        $notifications = [];
        $studentId = $user->studentProfile?->id;
        
        if (! $studentId) {
            return $notifications;
        }
        
        // Notifikasi kehadiran hari ini
        $attendances = Attendance::where('student_id', $studentId)
            ->whereDate('date', $date)
            ->with('schedule.subject:id,name')
            ->get();
        
        foreach ($attendances as $attendance) {
            $type = match ($attendance->status) {
                'present' => 'hadir', // NotifikasiSemua might treat 'hadir' as default/other if not 'tepat_waktu'
                'late' => 'terlambat',
                'sick' => 'sakit',
                'excused' => 'rapor', // Mapping 'izin' to rapor for now to ensure visibility
                'absent' => 'alpha',
                default => 'other',
            };
            
            // Adjust type for exact match if needed
            if ($attendance->status == 'present' && $attendance->check_in_time <= $attendance->schedule->start_time) {
                 $type = 'tepat_waktu';
            } elseif ($attendance->status == 'present') {
                 $type = 'tepat_waktu'; // Fallback
            }

            $message = match ($attendance->status) {
                'present' => 'Anda hadir tepat waktu',
                'late' => 'Anda terlambat',
                'sick' => 'Anda sakit',
                'excused' => 'Anda izin',
                'absent' => 'Anda tidak hadir',
                default => 'Status kehadiran',
            };
            
            $notifications[] = [
                'id' => $attendance->id,
                'type' => $type,
                'message' => $message,
                'detail' => 'Mata pelajaran '.$attendance->schedule->subject->name ?? 'Unknown',
                'time' => $attendance->date->format('H:i'),
                'created_at' => $attendance->created_at->toIso8601String(),
            ];
        }
        
        return $notifications;
    }
}

