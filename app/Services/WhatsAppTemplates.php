<?php

namespace App\Services;

use Carbon\Carbon;

class WhatsAppTemplates
{
    /**
     * Attendance success notification for parents
     */
    public static function attendanceSuccess(string $studentName, string $time, string $status = 'Hadir'): string
    {
        return "✅ *Notifikasi Absensi*\n\n".
               "Ananda *{$studentName}* telah melakukan absensi.\n\n".
               "📅 Waktu: {$time}\n".
               "📊 Status: *{$status}*\n\n".
               '_Terima kasih atas perhatiannya._';
    }

    /**
     * Late attendance notification for parents
     */
    public static function lateAttendance(string $studentName, string $time, string $scheduledTime): string
    {
        return "⚠️ *Notifikasi Keterlambatan*\n\n".
               "Ananda *{$studentName}* terlambat hadir.\n\n".
               "⏰ Jadwal: {$scheduledTime}\n".
               "📅 Absen: {$time}\n\n".
               '_Mohon perhatian untuk kedisiplinan waktu._';
    }

    /**
     * Absence notification for homeroom teacher
     */
    public static function absenceNotification(string $studentName, string $className, string $status): string
    {
        $emoji = match ($status) {
            'Sakit' => '🤒',
            'Izin' => '📝',
            'Alpha' => '❌',
            default => '⚠️',
        };

        return "{$emoji} *Notifikasi Ketidakhadiran*\n\n".
               "Siswa: *{$studentName}*\n".
               "Kelas: {$className}\n".
               "Status: *{$status}*\n\n".
               '_Mohon tindak lanjut sesuai prosedur._';
    }

    /**
     * QR Code generated notification for teacher
     */
    public static function qrCodeGenerated(string $subjectName, string $className, string $expiryTime): string
    {
        return "🔐 *QR Code Absensi Dibuat*\n\n".
               "Mata Pelajaran: *{$subjectName}*\n".
               "Kelas: {$className}\n".
               "⏰ Berlaku hingga: {$expiryTime}\n\n".
               '_QR Code terlampir. Silakan tampilkan kepada siswa._';
    }

    /**
     * Daily attendance report for homeroom teacher
     */
    public static function dailyReport(
        string $className,
        int $totalStudents,
        int $present,
        int $sick,
        int $permission,
        int $absent,
        string $date
    ): string {
        $percentage = $totalStudents > 0 ? round(($present / $totalStudents) * 100, 1) : 0;

        return "📊 *Laporan Absensi Harian*\n\n".
               "Kelas: *{$className}*\n".
               "Tanggal: {$date}\n\n".
               "👥 Total Siswa: {$totalStudents}\n".
               "✅ Hadir: {$present} ({$percentage}%)\n".
               "🤒 Sakit: {$sick}\n".
               "📝 Izin: {$permission}\n".
               "❌ Alpha: {$absent}\n\n".
               '_Laporan otomatis dari sistem absensi._';
    }

    /**
     * Reminder for students to check in
     */
    public static function attendanceReminder(string $studentName, string $subjectName, string $time): string
    {
        return "⏰ *Pengingat Absensi*\n\n".
               "Halo *{$studentName}*,\n\n".
               "Jangan lupa absen untuk:\n".
               "📚 {$subjectName}\n".
               "🕐 {$time}\n\n".
               '_Scan QR code yang ditampilkan oleh guru._';
    }

    /**
     * Weekly attendance summary for parents
     */
    public static function weeklySummary(
        string $studentName,
        string $weekRange,
        int $present,
        int $total,
        int $late = 0
    ): string {
        $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;

        return "📈 *Ringkasan Absensi Mingguan*\n\n".
               "Ananda: *{$studentName}*\n".
               "Periode: {$weekRange}\n\n".
               "✅ Hadir: {$present}/{$total} ({$percentage}%)\n".
               ($late > 0 ? "⚠️ Terlambat: {$late}x\n" : '').
               "\n_Terima kasih atas perhatiannya._";
    }

    /**
     * Format time for Indonesian locale
     */
    protected static function formatTime(Carbon $time): string
    {
        return $time->locale('id')->isoFormat('dddd, D MMMM Y - HH:mm');
    }
}
