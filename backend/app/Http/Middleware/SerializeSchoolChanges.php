<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SerializeSchoolChanges
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() && ! $request->is('api/*')) {
            return $next($request);
        }

        // Use the same lock for Filament writes and teacher reads/submissions. This
        // keeps multi-query snapshots consistent and checks revisions before writing.
        return DB::transaction(function () use ($request, $next) {
            DB::table('school_sync_state')->where('id', 1)->lockForUpdate()->first();

            return $next($request);
        }, 3);
    }
}
