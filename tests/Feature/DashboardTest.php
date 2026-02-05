<?php

use App\Models\Classes;
use App\Models\Schedule;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;

beforeEach(function () {
    $this->class = Classes::create(['grade' => '10', 'label' => 'A']);
});

test('admin can view admin summary', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);
    
    $response = $this->actingAs($admin)
        ->getJson('/api/admin/summary');

    $response->assertOk()
        ->assertJsonStructure([
            'students_count',
            'teachers_count',
            'classes_count',
            'majors_count',
            'rooms_count'
        ]);
});

test('student can view dashboard summary', function () {
    $studentUser = User::factory()->create(['user_type' => 'student']);
    $student = $studentUser->studentProfile()->create([
        'nis' => '12345',
        'class_id' => $this->class->id
    ]);

    // Create a schedule for today
    $teacher = User::factory()->create(['user_type' => 'teacher'])->teacherProfile()->create(['nip' => 'T1']);
    Schedule::create([
        'day' => now()->format('l'), // Today
        'start_time' => '07:00',
        'end_time' => '09:00',
        'teacher_id' => $teacher->id,
        'class_id' => $this->class->id,
        'semester' => 1,
        'year' => 2026,
        'subject_name' => 'Math'
    ]);

    $response = $this->actingAs($studentUser)
        ->getJson('/api/me/dashboard/summary');

    $response->assertOk()
        ->assertJsonStructure([
            'date',
            'student',
            'schedule_today'
        ])
        ->assertJsonPath('student.nis', '12345');
});

test('teacher can view teacher dashboard summary', function () {
    $teacherUser = User::factory()->create(['user_type' => 'teacher']);
    $teacher = $teacherUser->teacherProfile()->create(['nip' => 'T1']);

    // Create a teaching schedule for today
    Schedule::create([
        'day' => now()->format('l'),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'teacher_id' => $teacher->id,
        'class_id' => $this->class->id,
        'semester' => 1,
        'year' => 2026,
        'subject_name' => 'Physics'
    ]);

    $response = $this->actingAs($teacherUser)
        ->getJson('/api/me/dashboard/teacher-summary');

    $response->assertOk()
        ->assertJsonStructure([
            'teacher',
            'attendance_summary',
            'schedule_today'
        ]);
});

test('homeroom teacher can view dashboard', function () {
    $teacherUser = User::factory()->create(['user_type' => 'teacher']);
    $teacherUser->teacherProfile()->create([
        'nip' => 'T2',
        'homeroom_class_id' => $this->class->id
    ]);

    $response = $this->actingAs($teacherUser)
        ->getJson('/api/me/homeroom/dashboard');

    $response->assertOk()
        ->assertJsonPath('homeroom_class.id', $this->class->id);
});
