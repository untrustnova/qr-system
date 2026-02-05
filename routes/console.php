<?php

use App\Models\Attendance;
use App\Models\Qrcode;
use App\Models\Schedule;
use App\Models\StudentProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

ScheduleFacade::call(function () {
    Qrcode::where('is_active', true)
        ->where('expires_at', '<', now())
        ->update(['is_active' => false, 'status' => 'expired']);
})->everyMinute();

ScheduleFacade::call(function () {
    $today = now()->toDateString();
    $dayName = now()->format('l');

    $schedules = Schedule::where('day', $dayName)->get();

    foreach ($schedules as $schedule) {
        $studentIds = StudentProfile::where('class_id', $schedule->class_id)->pluck('id');

        foreach ($studentIds as $studentId) {
            Attendance::firstOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'student_id' => $studentId,
                    'date' => $today,
                ],
                [
                    'attendee_type' => 'student',
                    'status' => 'absent',
                    'source' => 'system',
                ]
            );
        }
    }
})->dailyAt('16:00'); // Assuming school ends by 16:00
