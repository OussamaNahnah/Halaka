<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_in_student_session_id')->constrained('student_sessions')->restrictOnDelete();
            $table->string('surah_name');
            $table->unsignedInteger('from_ayah');
            $table->unsignedInteger('to_ayah');
            $table->enum('type', ['new_memorization', 'revision']);
            $table->foreignId('repeat_of_assessment_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_assignments');
    }
};
