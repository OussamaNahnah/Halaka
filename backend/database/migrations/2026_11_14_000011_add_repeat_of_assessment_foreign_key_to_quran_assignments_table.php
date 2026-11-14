<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_assignments', function (Blueprint $table) {
            $table->foreign('repeat_of_assessment_id')->references('id')->on('quran_assessments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quran_assignments', function (Blueprint $table) {
            $table->dropForeign(['repeat_of_assessment_id']);
        });
    }
};
