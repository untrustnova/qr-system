<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Major;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function adminSummary(): JsonResponse
    {
        return response()->json([
            'students_count' => StudentProfile::count(),
            'teachers_count' => TeacherProfile::count(),
            'classes_count' => Classes::count(),
            'majors_count' => Major::count(),
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
}
