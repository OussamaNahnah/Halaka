<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The teacher frontend is built separately from teacher-app/ into public/teacher.
Route::get('/teacher', function () {
    $index = public_path('teacher/index.html');
    abort_unless(is_file($index), 503, 'تطبيق الأستاذ يحتاج البناء أولًا.');

    return response()->file($index, [
        'Cache-Control' => 'no-cache',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; worker-src 'self'; manifest-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
        'Referrer-Policy' => 'same-origin',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});
