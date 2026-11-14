<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('quran_assignment_id')->constrained()->restrictOnDelete();
            $table->double('grade');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_assessments');
    }
};
