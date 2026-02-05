<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->foreignId('class_id')->nullable()->change();
            $table->foreignId('teacher_id')->nullable()->after('student_id')->constrained('teacher_profiles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('teacher_id');
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreignId('class_id')->nullable(false)->change();
        });
    }
};
