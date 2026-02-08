<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeacherResource;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    public function index(): JsonResponse
    {
        $teachers = TeacherProfile::query()->with(['user', 'homeroomClass'])->latest()->paginate();

        return TeacherResource::collection($teachers)->response();
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.username' => ['required', 'string', 'max:50', 'distinct', 'unique:users,username'],
            'items.*.email' => ['nullable', 'email'],
            'items.*.password' => ['nullable', 'string', 'min:6'],
            'items.*.nip' => ['required', 'string', 'distinct', 'unique:teacher_profiles,nip'],
            'items.*.phone' => ['nullable', 'string', 'max:30'],
            'items.*.contact' => ['nullable', 'string', 'max:50'],
            'items.*.homeroom_class_id' => ['nullable', 'exists:classes,id'],
            'items.*.subject' => ['nullable', 'string', 'max:100'],
        ]);

        $created = collect();

        DB::transaction(function () use ($created, $data): void {
            foreach ($data['items'] as $item) {
                $user = User::create([
                    'name' => $item['name'],
                    'username' => $item['username'],
                    'email' => $item['email'] ?? null,
                    'password' => Hash::make($item['password'] ?? 'password123'),
                    'phone' => $item['phone'] ?? null,
                    'contact' => $item['contact'] ?? null,
                    'user_type' => 'teacher',
                ]);

                $created->push($user->teacherProfile()->create([
                    'nip' => $item['nip'],
                    'homeroom_class_id' => $item['homeroom_class_id'] ?? null,
                    'subject' => $item['subject'] ?? null,
                ]));
            }
        });

        return response()->json([
            'created' => $created->count(),
            'teachers' => $created->load(['user', 'homeroomClass']),
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'nip' => ['required', 'string', 'unique:teacher_profiles,nip'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact' => ['nullable', 'string', 'max:50'],
            'homeroom_class_id' => ['nullable', 'exists:classes,id'],
            'subject' => ['nullable', 'string', 'max:100'],
        ]);

        $teacher = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'contact' => $data['contact'] ?? null,
                'user_type' => 'teacher',
            ]);

            return $user->teacherProfile()->create([
                'nip' => $data['nip'],
                'homeroom_class_id' => $data['homeroom_class_id'] ?? null,
                'subject' => $data['subject'] ?? null,
            ]);
        });

        return response()->json($teacher->load(['user', 'homeroomClass']), 201);
    }

    public function show(TeacherProfile $teacher): JsonResponse
    {
        return response()->json($teacher->load(['user', 'homeroomClass', 'schedules']));
    }

    public function update(Request $request, TeacherProfile $teacher): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'password' => ['nullable', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contact' => ['nullable', 'string', 'max:50'],
            'homeroom_class_id' => ['nullable', 'exists:classes,id'],
            'subject' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($data, $teacher): void {
            if (isset($data['name']) || isset($data['email']) || isset($data['password']) || isset($data['phone']) || isset($data['contact'])) {
                $teacher->user->update([
                    'name' => $data['name'] ?? $teacher->user->name,
                    'email' => $data['email'] ?? $teacher->user->email,
                    'password' => isset($data['password']) ? Hash::make($data['password']) : $teacher->user->password,
                    'phone' => $data['phone'] ?? $teacher->user->phone,
                    'contact' => $data['contact'] ?? $teacher->user->contact,
                ]);
            }

            $teacher->update([
                'homeroom_class_id' => $data['homeroom_class_id'] ?? $teacher->homeroom_class_id,
                'subject' => $data['subject'] ?? $teacher->subject,
            ]);
        });

        return response()->json($teacher->fresh()->load(['user', 'homeroomClass']));
    }

    public function destroy(TeacherProfile $teacher): JsonResponse
    {
        $teacher->user()->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function uploadScheduleImage(Request $request, TeacherProfile $teacher): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:2048',
        ]);

        if ($teacher->schedule_image_path) {
            Storage::disk('public')->delete($teacher->schedule_image_path);
        }

        $path = $request->file('file')->store('schedules/teachers', 'public');
        $teacher->update(['schedule_image_path' => $path]);

        return response()->json(['url' => asset('storage/'.$path)]);
    }

    public function uploadMyScheduleImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->teacherProfile) {
            return response()->json(['message' => 'Teacher profile not found'], 404);
        }

        return $this->uploadScheduleImage($request, $user->teacherProfile);
    }

    public function getScheduleImage(TeacherProfile $teacher)
    {
        if (! $teacher->schedule_image_path || ! Storage::disk('public')->exists($teacher->schedule_image_path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        return response()->file(Storage::disk('public')->path($teacher->schedule_image_path));
    }

    public function deleteScheduleImage(TeacherProfile $teacher): JsonResponse
    {
        if ($teacher->schedule_image_path) {
            Storage::disk('public')->delete($teacher->schedule_image_path);
            $teacher->update(['schedule_image_path' => null]);
        }

        return response()->json(['message' => 'Image deleted']);
    }

    public function attendance(Request $request, TeacherProfile $teacher): JsonResponse
    {
        // Waka/Admin viewing teacher's attendance history
        $query = \App\Models\Attendance::where('teacher_id', $teacher->id)
            ->where('attendee_type', 'teacher');

        if ($request->filled('month')) {
            $query->whereMonth('date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        $attendances = $query->with(['schedule.class', 'schedule.subject'])
            ->latest('date')
            ->get();

        return response()->json($attendances);
    }

    // Walikelas endpoints
    public function myHomeroom(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->teacherProfile || ! $user->teacherProfile->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom not found'], 404);
        }

        return response()->json($user->teacherProfile->homeroomClass->load('major'));
    }

    public function myHomeroomSchedules(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->teacherProfile || ! $user->teacherProfile->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom not found'], 404);
        }

        $class = $user->teacherProfile->homeroomClass;
        $query = $class->schedules();

        if ($request->filled('date')) {
            $day = date('l', strtotime($request->date));
            $query->where('day', $day);
        }

        return response()->json($query->with(['subject', 'teacher.user'])->get());
    }

    public function myHomeroomStudents(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->teacherProfile || ! $user->teacherProfile->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom not found'], 404);
        }

        return response()->json($user->teacherProfile->homeroomClass->students->load('user'));
    }

    public function myHomeroomAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->teacherProfile || ! $user->teacherProfile->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom not found'], 404);
        }

        $classId = $user->teacherProfile->homeroom_class_id;

        $query = \App\Models\Attendance::whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->with(['student.user', 'schedule.subject', 'schedule.teacher.user'])->latest()->get());
    }

    public function myHomeroomAttendanceSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->teacherProfile || ! $user->teacherProfile->homeroom_class_id) {
            return response()->json(['message' => 'Homeroom not found'], 404);
        }

        $classId = $user->teacherProfile->homeroom_class_id;

        $query = \App\Models\Attendance::whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        });

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
     * Get students requiring follow-up (Mobile App)
     * Returns students with concerning attendance patterns
     */
    public function getStudentsFollowUp(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $user->teacherProfile;

        if (! $teacher) {
            return response()->json(['message' => 'Teacher profile not found'], 404);
        }

        // Get all students from classes taught by this teacher
        $schedules = \App\Models\Schedule::where('teacher_id', $teacher->id)->get();
        $classIds = $schedules->pluck('class_id')->unique();

        $query = \App\Models\StudentProfile::whereIn('class_id', $classIds)
            ->with(['user', 'classRoom']);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('nis', 'like', "%{$search}%");
        }

        $students = $query->get()->map(function ($student) {
            // Get attendance summary for this student
            $attendanceSummary = \App\Models\Attendance::where('student_id', $student->id)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            $absent = $attendanceSummary->get('absent', 0);
            $excused = $attendanceSummary->get('excused', 0) + $attendanceSummary->get('izin', 0);
            $sick = $attendanceSummary->get('sick', 0);

            // Calculate badge based on attendance pattern
            if ($absent >= 1) {
                $badge = [
                    'type' => 'danger',
                    'label' => 'Sering Absensi',
                ];
                $severityScore = ($absent * 100) + ($excused * 10) + ($sick * 5);
            } elseif ($excused > 5) {
                $badge = [
                    'type' => 'warning',
                    'label' => 'Perlu Diperhatikan',
                ];
                $severityScore = ($excused * 10) + ($sick * 5);
            } else {
                $badge = [
                    'type' => 'success',
                    'label' => 'Aman',
                ];
                $severityScore = ($excused * 10) + ($sick * 5);
            }

            return [
                'id' => $student->id,
                'name' => $student->user->name,
                'nis' => $student->nis,
                'class_name' => $student->classRoom?->name ?? 'N/A',
                'attendance_summary' => [
                    'absent' => $absent,
                    'excused' => $excused,
                    'sick' => $sick,
                ],
                'badge' => $badge,
                'severity_score' => $severityScore,
            ];
        })->sortByDesc('severity_score')->values();

        return response()->json(['data' => $students]);
    }
}
