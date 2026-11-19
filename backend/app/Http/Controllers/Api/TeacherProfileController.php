<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherProfileResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TeacherProfileController extends Controller
{
    public function show(Request $request)
    {
        return new TeacherProfileResource($request->user());
    }

    public function password(Request $request)
    {
        $values = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed']]);
        if (! Hash::check($values['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }
        $request->user()->update(['password' => $values['password']]);
        Auth::guard('teacher_api')->logout();

        return response()->json(['message' => 'تم تغيير كلمة المرور. سجل الدخول مجددًا.']);
    }
}
