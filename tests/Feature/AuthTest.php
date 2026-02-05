<?php

use App\Models\Classes;
use App\Models\User;

test('users can login and get correct role', function () {
    $admin = User::factory()->create(['user_type' => 'admin', 'password' => 'password']);

    $response = $this->postJson('/api/auth/login', [
        'login' => $admin->username,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.role', 'admin');
});

test('teacher returns wali role if homeroom', function () {
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $teacher = User::factory()->create(['user_type' => 'teacher', 'password' => 'password']);
    $teacher->teacherProfile()->create([
        'nip' => '12345',
        'homeroom_class_id' => $class->id,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'login' => $teacher->username,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.role', 'wali');
});

test('student returns pengurus role if class officer', function () {
    $class = Classes::create(['grade' => '10', 'label' => 'A']);
    $student = User::factory()->create(['user_type' => 'student', 'password' => 'password']);
    $student->studentProfile()->create([
        'nis' => '123456',
        'class_id' => $class->id,
        'is_class_officer' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'login' => $student->username,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.role', 'pengurus');
});
