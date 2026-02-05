<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('user can change password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ]);

    $response->assertOk();

    $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
});

test('user can update profile', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $response = $this->actingAs($user)
        ->postJson('/api/me/profile', [
            'email' => 'new@example.com',
            'phone' => '08123456789',
        ]);

    $response->assertOk();
    $this->assertEquals('new@example.com', $user->fresh()->email);
    $this->assertEquals('08123456789', $user->fresh()->phone);
});

test('teacher can upload schedule image', function () {
    Storage::fake('local');

    $teacherUser = User::factory()->create(['user_type' => 'teacher']);
    $teacher = $teacherUser->teacherProfile()->create(['nip' => 'T1']);

    $file = UploadedFile::fake()->image('schedule.jpg');

    $response = $this->actingAs($teacherUser)
        ->postJson('/api/me/schedule-image', [
            'file' => $file,
        ]);

    $response->assertOk();

    $this->assertNotNull($teacher->fresh()->schedule_image_path);
    Storage::disk('local')->assertExists($teacher->fresh()->schedule_image_path);
});
