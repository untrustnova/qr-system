<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date');
            $table->foreignId('student_id')->nullable()->constrained('student_profiles')->onDelete('set null');
            $table->foreignId('teacher_id')->nullable()->constrained('teacher_profiles')->onDelete('set null');
            $table->foreignId('qrcode_id')->nullable()->constrained('qrcodes')->onDelete('set null');
            $table->text('reason')->nullable();
            $table->string('reason_file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
