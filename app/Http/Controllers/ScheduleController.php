<?php

namespace App\Http\Controllers;

use App\Events\SchedulesBulkUpdated;
use App\Models\Classes;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::query()->with(['teacher.user', 'class']);

        if ($request->user()->user_type === 'teacher') {
            $query->where('teacher_id', optional($request->user()->teacherProfile)->id);
        }

        if ($request->user()->user_type === 'student') {
            $classId = optional($request->user()->studentProfile)->class_id;
            if ($classId) {
                $query->where('class_id', $classId);
            }
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }

        if ($request->filled('date')) {
            $day = Carbon::parse($request->string('date'))->format('l');
            $query->where('day', $day);
        }

        return response()->json($query->latest()->paginate());
    }

    public function byTeacher(Request $request, TeacherProfile $teacher): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Schedule::query()
            ->with(['teacher.user', 'class'])
            ->where('teacher_id', $teacher->id);

        if ($request->filled('from')) {
            $from = Carbon::parse($request->string('from'))->format('l');
            $query->where('day', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse($request->string('to'))->format('l');
            $query->where('day', $to);
        }

        $perPage = $this->resolvePerPage($request);
        $query->orderBy('day')->orderBy('start_time');

        return response()->json($perPage ? $query->paginate($perPage) : $query->get());
    }

    public function byClass(Request $request, Classes $class): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Schedule::query()
            ->with(['teacher.user', 'class'])
            ->where('class_id', $class->id);

        if ($request->filled('from')) {
            $from = Carbon::parse($request->string('from'))->format('l');
            $query->where('day', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse($request->string('to'))->format('l');
            $query->where('day', $to);
        }

        $perPage = $this->resolvePerPage($request);
        $query->orderBy('day')->orderBy('start_time');

        return response()->json($perPage ? $query->paginate($perPage) : $query->get());
    }

    public function me(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'student' || ! $request->user()->studentProfile) {
            abort(403, 'Hanya untuk siswa');
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : now();

        $day = $date->format('l');

        $schedules = Schedule::query()
            ->with(['teacher.user', 'class'])
            ->where('class_id', $request->user()->studentProfile->class_id)
            ->where('day', $day)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'date' => $date->toDateString(),
            'day' => $day,
            'items' => $schedules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'day' => ['required', 'string'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'title' => ['nullable', 'string', 'max:255'],
            'subject_name' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:teacher_profiles,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'room' => ['nullable', 'string', 'max:50'],
            'semester' => ['required', 'integer'],
            'year' => ['required', 'integer'],
        ]);

        if (isset($data['subject_id']) && ! isset($data['subject_name'])) {
            $subject = Subject::find($data['subject_id']);
            $data['subject_name'] = $subject?->name;
        }

        if (! isset($data['title'])) {
            $data['title'] = $data['subject_name'] ?? 'Mata Pelajaran';
        }

        $schedule = Schedule::create($data);

        return response()->json($schedule->load(['teacher.user', 'class']), 201);
    }

    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->user_type === 'teacher' && $schedule->teacher_id !== optional($request->user()->teacherProfile)->id) {
            abort(403, 'Tidak boleh melihat jadwal guru lain');
        }

        if ($request->user()->user_type === 'student' && $schedule->class_id !== optional($request->user()->studentProfile)->class_id) {
            abort(403, 'Hanya boleh melihat jadwal kelas sendiri');
        }

        return response()->json($schedule->load(['teacher.user', 'class', 'qrcodes', 'attendances']));
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'day' => ['sometimes', 'string'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'title' => ['nullable', 'string', 'max:255'],
            'subject_name' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'teacher_id' => ['sometimes', 'exists:teacher_profiles,id'],
            'class_id' => ['sometimes', 'exists:classes,id'],
            'room' => ['nullable', 'string', 'max:50'],
            'semester' => ['sometimes', 'integer'],
            'year' => ['sometimes', 'integer'],
        ]);

        if (isset($data['subject_id']) && ! isset($data['subject_name'])) {
            $subject = Subject::find($data['subject_id']);
            $data['subject_name'] = $subject?->name;
        }

        if (array_key_exists('subject_name', $data) && ! isset($data['title'])) {
            $data['title'] = $data['subject_name'];
        }

        $schedule->update($data);

        return response()->json($schedule->load(['teacher.user', 'class']));
    }

    public function bulkUpsert(Request $request, Classes $class): JsonResponse
    {
        $data = $request->validate([
            'day' => ['required', 'string'],
            'semester' => ['required', 'integer'],
            'year' => ['required', 'integer'],
            'items' => ['required', 'array'],
            'items.*.subject_name' => ['nullable', 'string', 'max:255'],
            'items.*.subject_id' => ['nullable', 'exists:subjects,id'],
            'items.*.teacher_id' => ['required', 'exists:teacher_profiles,id'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.end_time' => ['required', 'date_format:H:i'],
            'items.*.room' => ['nullable', 'string', 'max:50'],
        ]);

        $day = $this->normalizeDay($data['day']);

        foreach ($data['items'] as $index => $item) {
            $start = Carbon::createFromFormat('H:i', $item['start_time']);
            $end = Carbon::createFromFormat('H:i', $item['end_time']);

            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages([
                    'items.'.$index.'.end_time' => ['End time must be after start time.'],
                ]);
            }
        }

        $created = collect();

        DB::transaction(function () use ($class, $day, $data, $created): void {
            $class->schedules()
                ->where('day', $day)
                ->where('semester', $data['semester'])
                ->where('year', $data['year'])
                ->delete();

            foreach ($data['items'] as $item) {
                $subjectName = $item['subject_name'] ?? null;

                if (isset($item['subject_id']) && ! $subjectName) {
                    $subject = Subject::find($item['subject_id']);
                    $subjectName = $subject?->name;
                }

                $created->push(Schedule::create([
                    'day' => $day,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'title' => $subjectName ?? 'Mata Pelajaran',
                    'subject_name' => $subjectName,
                    'teacher_id' => $item['teacher_id'],
                    'class_id' => $class->id,
                    'room' => $item['room'] ?? null,
                    'semester' => $data['semester'],
                    'year' => $data['year'],
                ]));
            }
        });

        $createdIds = $created->pluck('id')->all();
        $schedules = Schedule::with(['teacher.user', 'class'])
            ->whereIn('id', $createdIds)
            ->get();

        Log::info('schedules.bulk.updated', [
            'class_id' => $class->id,
            'day' => $day,
            'semester' => $data['semester'],
            'year' => $data['year'],
            'count' => $created->count(),
            'user_id' => $request->user()->id,
        ]);

        SchedulesBulkUpdated::dispatch($class->id, $day, $data['semester'], $data['year'], $created->count());

        return response()->json([
            'class_id' => $class->id,
            'day' => $day,
            'semester' => $data['semester'],
            'year' => $data['year'],
            'count' => $created->count(),
            'schedules' => $schedules,
        ]);
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function normalizeDay(string $day): string
    {
        $map = [
            'senin' => 'Monday',
            'selasa' => 'Tuesday',
            'rabu' => 'Wednesday',
            'kamis' => 'Thursday',
            'jumat' => 'Friday',
            'jum\'at' => 'Friday',
        ];

        $lower = strtolower($day);

        return $map[$lower] ?? $day;
    }

    private function resolvePerPage(Request $request): ?int
    {
        if (! $request->filled('per_page') && ! $request->filled('page')) {
            return null;
        }

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = $request->integer('per_page', 15);

        return min(max($perPage, 1), 200);
    }
}
