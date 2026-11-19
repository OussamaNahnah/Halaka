<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TeacherAuthController extends Controller
{
    public function login(Request $request)
    {
        $values = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1024']]);
        $user = User::where('email', $values['email'])->first();
        // Always perform a hash check, including unknown accounts.
        $valid = Hash::check($values['password'], $user?->password ?? '$2y$12$c5cR9ooheBJZWvUbx7c05.nfxKXS7ukHIZ.XqWdzXEeIXgIkmFEUu');
        if (! $valid || ! $user?->teacher || ! $user->roles()->where('name', 'Teacher')->exists()) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة أو الحساب غير متاح.']);
        }
        $token = Auth::guard('teacher_api')->login($user);

        return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'data' => new TeacherProfileResource($user)])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request)
    {
        Auth::guard('teacher_api')->logout();

        return response()->noContent();
    }
}
