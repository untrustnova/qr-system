<?php

namespace App\Listeners;

use App\Events\AttendanceRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendAttendanceNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AttendanceRecorded $event): void
    {
        $attendance = $event->attendance;
        $user = $attendance->attendee_type === 'student'
            ? optional($attendance->student)->user
            : optional($attendance->teacher)->user;

        if (! $user || ! $user->phone) {
            return;
        }

        $baseUrl = config('services.whatsapp.base_url');
        if (! $baseUrl) {
            return;
        }

        $statusLabel = match ($attendance->status) {
            'present' => 'Hadir Tepat Waktu',
            'late' => 'Hadir Terlambat',
            'sick' => 'Sakit',
            'excused', 'izin' => 'Izin',
            'absent' => 'Alpha',
            'dinas' => 'Dinas',
            default => $attendance->status,
        };

        $message = sprintf(
            'Halo %s, presensi Anda untuk mata pelajaran %s pada tanggal %s telah tercatat dengan status: *%s*.',
            $user->name,
            $attendance->schedule->subject_name ?? $attendance->schedule->title,
            $attendance->date->format('d-m-Y'),
            $statusLabel
        );

        try {
            Http::timeout(10)
                ->withToken(config('services.whatsapp.token'))
                ->post(rtrim($baseUrl, '/').'/send-message', [
                    'to' => $user->phone,
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::error('failed.send.attendance.wa', [
                'attendance_id' => $attendance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
