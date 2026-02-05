<?php

use App\Models\User;

test('teacher can report own absence', function () {
    $teacherUser = User::factory()->create(['user_type' => 'teacher']);
    $teacher = $teacherUser->teacherProfile()->create(['nip' => 'T1']);

    $response = $this->actingAs($teacherUser)
        ->postJson('/api/absence-requests', [
            'type' => 'sick',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'reason' => 'Flu berat',
            // No student_id provided
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('absence_requests', [
        'teacher_id' => $teacher->id,
        'student_id' => null,
        'type' => 'sick',
        'reason' => 'Flu berat',
        'requested_by' => $teacherUser->id,
    ]);
});

test('student cannot report absence without ID', function () {
    $studentUser = User::factory()->create(['user_type' => 'student']);

    $response = $this->actingAs($studentUser)
        ->postJson('/api/absence-requests', [
            'type' => 'sick',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

    $response->assertStatus(422);
});
