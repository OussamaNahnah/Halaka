<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_assignments', function (Blueprint $table) {
            $table->foreignId('created_in_session_id')->nullable()->constrained('teaching_sessions')->restrictOnDelete();
        });

        DB::transaction(function () {
            DB::table('school_sync_state')->where('id', 1)->lockForUpdate()->first();
            DB::table('school_sync_state')->where('id', 1)->increment('revision');
        });
    }

    public function down(): void
    {
        Schema::table('quran_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_in_session_id');
        });

        DB::transaction(function () {
            DB::table('school_sync_state')->where('id', 1)->lockForUpdate()->first();
            DB::table('school_sync_state')->where('id', 1)->increment('revision');
        });
    }
};
