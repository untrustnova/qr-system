<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE attendances MODIFY status ENUM('present','late','excused','sick','absent','dinas','izin') NOT NULL DEFAULT 'present'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE attendances MODIFY status ENUM('present','late','excused','sick','absent') NOT NULL DEFAULT 'present'"
        );
    }
};
