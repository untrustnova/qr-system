<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeacherStatisticsController extends Controller
{
    /**
     * Get monthly attendance statistics for the authenticated teacher.
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacherProfile;

        if (! $teacher) {
            return response()->json(['message' => 'Teacher profile not found'], 404);
        }

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // Calculate statistics based on Attendance model
        // Assuming 'attendee_type' = 'teacher' implies teaching attendance
        $stats = Attendance::where('teacher_id', $teacher->id)
            ->where('attendee_type', 'teacher')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $present = $stats['present'] ?? 0;
        $late = $stats['late'] ?? 0;
        $sick = $stats['sick'] ?? 0;
        $permit = $stats['permit'] ?? 0; // or 'excused'
        $absent = $stats['absent'] ?? 0;

        // Calculate total teaching hours (based on schedules attended)
        // This is an approximation. For exact hours, we'd need duration from Schedule.
        // Let's assume 1 JP = 45 mins, or just count sessions for now.
        $totalSessions = $present + $late + $sick + $permit + $absent;

        return response()->json([
            'month' => $month,
            'year' => $year,
            'summary' => [
                'hadir' => $present,
                'terlambat' => $late,
                'sakit' => $sick,
                'izin' => $permit,
                'alpha' => $absent,
                'total_sessions' => $totalSessions,
            ],
            // Mock chart data for now, real data would require daily aggregation
            'chart_data' => $this->getDailyChartData($teacher->id, $month, $year),
        ]);
    }

    private function getDailyChartData($teacherId, $month, $year): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $data = [];

        // Get actual attendance counts per day
        $dailyCounts = Attendance::where('teacher_id', $teacherId)
            ->where('attendee_type', 'teacher')
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->selectRaw('DAY(date) as day, status')
            ->get()
            ->groupBy('day');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayData = $dailyCounts->get($day);
            $status = 'libur'; // Default

            if ($dayData && $dayData->isNotEmpty()) {
                // Determine dominant status for the day
                // Prioritize: present > late > sick > excused > absent
                $statuses = $dayData->pluck('status')->map(fn($s) => strtolower($s));
                
                if ($statuses->contains('present')) {
                    $status = 'hadir';
                } elseif ($statuses->contains('late')) {
                    $status = 'terlambat';
                } elseif ($statuses->contains('sick')) {
                    $status = 'sakit';
                } elseif ($statuses->contains('excused') || $statuses->contains('izin')) {
                    $status = 'izin';
                } elseif ($statuses->contains('absent')) {
                    $status = 'alpha';
                } else {
                    $status = 'hadir'; // Fallback if data exists
                }
            } else {
                // Check if it's a weekend (Saturday/Sunday)
                $date = Carbon::createFromDate($year, $month, $day);
                if ($date->isWeekend()) {
                    $status = 'libur';
                } else {
                    $status = 'alpha'; // Or 'belum absen' if today/future
                    if ($date->isFuture()) {
                        $status = 'future';
                    }
                }
            }

            // Mobile expects: { day: 1, status: "hadir" }
            $data[] = [
                'day' => $day,
                'status' => $status,
            ];
        }

        return $data;
    }
}
