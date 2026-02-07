<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Major;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HomeroomTeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan ada kelas
        $major = Major::firstOrCreate(
            ['code' => 'TKJ'],
            ['name' => 'Teknik Komputer dan Jaringan']
        );

        $class = Classes::firstOrCreate(
            [
                'grade' => '12',
                'label' => 'TKJ 1',
            ],
            [
                'major_id' => $major->id,
            ]
        );

        // Buat user wali kelas
        $user = User::updateOrCreate(
            ['username' => 'walikelas1'],
            [
                'name' => 'Wali Kelas TKJ 1',
                'email' => 'walikelas1@example.com',
                'password' => Hash::make('password123'),
                'user_type' => 'teacher',
                'active' => true,
            ]
        );

        TeacherProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nip' => 'NIP-WALI-001',
                'subject' => 'Matematika',
                'homeroom_class_id' => $class->id, // Set as homeroom teacher
            ]
        );
    }
}
