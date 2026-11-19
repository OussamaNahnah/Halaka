<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jwt_revocations', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->text('value');
            $table->timestamp('expires_at')->nullable();
        });
        Schema::create('school_sync_state', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('school_sync_state')->insert(['id' => 1, 'revision' => 0]);
        foreach (['teaching_sessions', 'quran_assignments', 'quran_assessments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('client_uuid')->nullable()->unique();
            });
        }
        Schema::table('teaching_sessions', fn (Blueprint $table) => $table->unsignedBigInteger('revision')->default(1));
        Schema::create('teacher_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->uuid('submission_id');
            $table->string('payload_hash', 64);
            $table->json('acknowledgement');
            $table->timestamps();
            $table->unique(['teacher_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_submissions');
        Schema::table('teaching_sessions', fn (Blueprint $table) => $table->dropColumn('revision'));
        foreach (['teaching_sessions', 'quran_assignments', 'quran_assessments'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropUnique($name.'_client_uuid_unique');
                $table->dropColumn('client_uuid');
            });
        }
        Schema::dropIfExists('school_sync_state');
        Schema::dropIfExists('jwt_revocations');
    }
};
