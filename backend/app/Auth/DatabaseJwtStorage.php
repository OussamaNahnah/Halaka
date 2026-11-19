<?php

namespace App\Auth;

use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Contracts\Providers\Storage;

class DatabaseJwtStorage implements Storage
{
    public function add($key, $value, $minutes)
    {
        $this->put($key, $value, now()->addMinutes($minutes));
    }

    public function forever($key, $value)
    {
        $this->put($key, $value, null);
    }

    private function put($key, $value, $expires): void
    {
        DB::table('jwt_revocations')->updateOrInsert(['id' => hash('sha256', $key)], ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'expires_at' => $expires]);
    }

    public function get($key)
    {
        $row = DB::table('jwt_revocations')->where('id', hash('sha256', $key))->first();

        return $row && (! $row->expires_at || now()->lt($row->expires_at)) ? json_decode($row->value, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function destroy($key)
    {
        return (bool) DB::table('jwt_revocations')->where('id', hash('sha256', $key))->delete();
    }

    public function flush()
    {
        DB::table('jwt_revocations')->delete();
    }
}
