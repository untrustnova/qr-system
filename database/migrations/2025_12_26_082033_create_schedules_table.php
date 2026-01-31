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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('title');
            $table->foreignId('teacher_id')->constrained('teacher_profiles')->onDelete('restrict');
            $table->foreignId('class_id')->constrained('classes')->onDelete('restrict');
            $table->string('room')->nullable();
            $table->integer('semester');
            $table->year('year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
