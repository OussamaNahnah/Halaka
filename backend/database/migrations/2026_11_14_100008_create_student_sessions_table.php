<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('session_id')->constrained('teaching_sessions')->restrictOnDelete();
            $table->enum('attendance_status', ['not_marked', 'present', 'absent'])->default('not_marked');
            $table->timestamp('evaluated_at')->nullable();
            $table->unique(['student_id', 'session_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_sessions');
    }
};
