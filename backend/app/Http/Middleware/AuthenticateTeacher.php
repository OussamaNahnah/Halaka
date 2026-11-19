<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthenticateTeacher
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->bearerToken(), 401, 'يلزم تسجيل الدخول.');
        $guard = Auth::guard('teacher_api');
        $guard->forgetUser();
        try {
            $user = $guard->setToken($request->bearerToken())->user();
            $payload = $guard->payload();
        } catch (JWTException $exception) {
            abort(401, 'رمز الدخول غير صالح.');
        }
        abort_unless($user && hash_equals($user->jwtPasswordFingerprint(), (string) $payload->get('pwd')) && (array) $payload->get('aud') === ['halaka-teacher'], 401, 'يلزم تسجيل الدخول مجددًا.');
        abort_unless($user->teacher && $user->roles()->where('name', 'Teacher')->exists(), 403, 'هذا الحساب غير متاح للأستاذ.');
        Auth::shouldUse('teacher_api');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
