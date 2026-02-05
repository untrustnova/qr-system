<?php

use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin can upload and download attendance attachment', function () {
    Storage::fake('local');
    
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $teacher = User::factory()->create(['user_type' => 'teacher'])->teacherProfile()->create(['nip' => 'T1']);
    
    $schedule = Schedule::create([
        'day' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'semester' => 1,
        'year' => 2026,
    ]);

    $studentUser = User::factory()->create(['user_type' => 'student']);
    $student = $studentUser->studentProfile()->create(['nis' => 'S1', 'class_id' => $class->id]);

    $attendance = Attendance::create([
        'attendee_type' => 'student',
        'student_id' => $student->id,
        'schedule_id' => $schedule->id,
        'status' => 'sick',
        'date' => now(),
        'source' => 'manual'
    ]);

    $admin = User::factory()->create(['user_type' => 'admin']);

    // 1. Upload Attachment
    $file = UploadedFile::fake()->create('doctor_note.pdf', 100);
    $response = $this->actingAs($admin)
        ->postJson("/api/attendance/{$attendance->id}/attachments", [
            'file' => $file
        ]);

    $response->assertCreated();
    
    // 2. Download Attachment securely
    $downloadResponse = $this->actingAs($admin)
        ->get("/api/attendance/{$attendance->id}/document");

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-disposition', 'attachment; filename=doctor_note.pdf');
});

test('unauthorized user cannot download attachment', function () {
    Storage::fake('local');
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $teacher = User::factory()->create(['user_type' => 'teacher'])->teacherProfile()->create(['nip' => 'T1']);
    $schedule = Schedule::create([
        'day' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00',
        'teacher_id' => $teacher->id, 'class_id' => $class->id, 'semester' => 1, 'year' => 2026
    ]);
    
    $studentUser = User::factory()->create(['user_type' => 'student']);
    $student = $studentUser->studentProfile()->create(['nis' => 'S1', 'class_id' => $class->id]);
    
    $attendance = Attendance::create([
        'attendee_type' => 'student', 'student_id' => $student->id, 'schedule_id' => $schedule->id,
        'status' => 'sick', 'date' => now(), 'source' => 'manual'
    ]);

    // Create attachment manually
    $file = UploadedFile::fake()->create('note.pdf');
    $path = $file->store('attendance-attachments', 'local');
    $attendance->attachments()->create([
        'path' => $path, 'original_name' => 'note.pdf', 'mime_type' => 'application/pdf', 'size' => 1024
    ]);

    // Another student tries to download
    $otherStudent = User::factory()->create(['user_type' => 'student']);
    
    $response = $this->actingAs($otherStudent)
        ->get("/api/attendance/{$attendance->id}/document");

    $response->assertForbidden();
});
