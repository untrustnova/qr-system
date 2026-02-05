<?php

use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Schedule;
use App\Models\User;

test('teacher receives teaching notifications', function () {
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $teacherUser = User::factory()->create(['user_type' => 'teacher']);
    $teacher = $teacherUser->teacherProfile()->create(['nip' => 'T1']);

    $schedule = Schedule::create([
        'day' => now()->format('l'),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'semester' => 1,
        'year' => 2026,
        'subject_name' => 'Physics',
    ]);

    // Teacher attended
    Attendance::create([
        'attendee_type' => 'teacher',
        'teacher_id' => $teacher->id,
        'schedule_id' => $schedule->id,
        'status' => 'present',
        'date' => now(),
        'checked_in_at' => now(),
        'source' => 'manual',
    ]);

    $response = $this->actingAs($teacherUser)
        ->getJson('/api/mobile/notifications');

    $response->assertOk()
        ->assertJsonFragment(['type' => 'tepat_waktu'])
        ->assertJsonFragment(['message' => 'Anda mengajar tepat waktu pada']);
});

test('student receives attendance notifications', function () {
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $studentUser = User::factory()->create(['user_type' => 'student']);
    $student = $studentUser->studentProfile()->create(['nis' => 'S1', 'class_id' => $class->id]);
    $teacher = User::factory()->create(['user_type' => 'teacher'])->teacherProfile()->create(['nip' => 'T1']);

    $schedule = Schedule::create([
        'day' => now()->format('l'),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'semester' => 1,
        'year' => 2026,
        'subject_name' => 'Physics',
    ]);

    // Student absent
    Attendance::create([
        'attendee_type' => 'student',
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => 'absent',
        'date' => now(),
        'source' => 'system',
    ]);

    $response = $this->actingAs($studentUser)
        ->getJson('/api/mobile/notifications');

    $response->assertOk()
        ->assertJsonFragment(['type' => 'alpha'])
        ->assertJsonFragment(['message' => 'Anda tidak hadir']);
});
