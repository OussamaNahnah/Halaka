<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SchoolSync
{
    public static function revision(): int
    {
        return (int) DB::table('school_sync_state')->where('id', 1)->value('revision');
    }

    public static function changed(): void
    {
        DB::table('school_sync_state')->where('id', 1)->increment('revision');
    }

    public static function meta(): array
    {
        return ['sync_revision' => self::revision()];
    }
}
