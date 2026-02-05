<?php

namespace App\Http\Controllers;

use App\Models\Classes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Classes::query()->with('major')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade' => ['required', 'string', 'max:10'],
            'label' => ['required', 'string', 'max:20'],
            'major_id' => ['nullable', 'exists:majors,id'],
        ]);

        $class = Classes::create($data);

        return response()->json($class, 201);
    }

    public function show(Classes $class): JsonResponse
    {
        return response()->json($class->load(['students', 'homeroomTeacher', 'major']));
    }

    public function update(Request $request, Classes $class): JsonResponse
    {
        $data = $request->validate([
            'grade' => ['sometimes', 'string', 'max:10'],
            'label' => ['sometimes', 'string', 'max:20'],
            'major_id' => ['nullable', 'exists:majors,id'],
        ]);

        $class->update($data);

        return response()->json($class);
    }

    public function destroy(Classes $class): JsonResponse
    {
        $class->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function uploadScheduleImage(Request $request, Classes $class): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:2048', // 2MB Max
        ]);

        if ($class->schedule_image_path) {
            Storage::disk('public')->delete($class->schedule_image_path);
        }

        $path = $request->file('file')->store('schedules/classes', 'public');
        $class->update(['schedule_image_path' => $path]);

        return response()->json(['url' => asset('storage/'.$path)]);
    }

    public function getScheduleImage(Classes $class)
    {
        if (! $class->schedule_image_path || ! Storage::disk('public')->exists($class->schedule_image_path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        return response()->file(Storage::disk('public')->path($class->schedule_image_path));
    }

    public function deleteScheduleImage(Classes $class): JsonResponse
    {
        if ($class->schedule_image_path) {
            Storage::disk('public')->delete($class->schedule_image_path);
            $class->update(['schedule_image_path' => null]);
        }

        return response()->json(['message' => 'Image deleted']);
    }

    public function myClass(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->studentProfile || ! $user->studentProfile->classRoom) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        return response()->json($user->studentProfile->classRoom->load('major'));
    }

    public function myClassSchedules(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->studentProfile || ! $user->studentProfile->class_id) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $query = $user->studentProfile->classRoom->schedules();

        if ($request->filled('date')) {
            // Assuming schedules have a day or date field, or we filter by day of week
            // But typical generic schedule is by day of week.
            // If date is provided, we map date to day of week.
            $day = date('l', strtotime($request->date));
            $query->where('day', $day);
        }

        return response()->json($query->with(['subject', 'teacher.user'])->get());
    }

    public function myClassAttendance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->studentProfile || ! $user->studentProfile->class_id) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        // This usually means getting the attendance records for the class
        // We need Attendance model, which is linked to Student, which is linked to Class.
        // Or Attendance linked to Schedule which is linked to Class.
        // Assuming we want attendance of students IN this class.

        $classId = $user->studentProfile->class_id;

        $query = \App\Models\Attendance::whereHas('student.classRoom', function ($q) use ($classId) {
            $q->where('id', $classId);
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

        return response()->json($query->with('student.user')->latest()->get());
    }
}
