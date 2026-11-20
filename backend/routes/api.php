<?php

use App\Http\Controllers\Api\TeacherAuthController;
use App\Http\Controllers\Api\TeacherClassroomController;
use App\Http\Controllers\Api\TeacherProfileController;
use App\Http\Controllers\Api\TeacherSessionController;
use App\Http\Controllers\Api\TeacherStudentController;
use App\Http\Middleware\AuthenticateTeacher;
use App\Http\Middleware\SerializeSchoolChanges;
use Illuminate\Support\Facades\Route;

Route::post('login', [TeacherAuthController::class, 'login'])->middleware('throttle:teacher-login');
Route::middleware([SerializeSchoolChanges::class, AuthenticateTeacher::class, 'throttle:teacher-api'])->group(function () {
    Route::post('logout', [TeacherAuthController::class, 'logout']);
    Route::get('profile', [TeacherProfileController::class, 'show']);
    Route::put('profile/password', [TeacherProfileController::class, 'password']);
    Route::get('groups', [TeacherClassroomController::class, 'groups']);
    Route::get('groups/{group}/students', [TeacherClassroomController::class, 'students'])->whereNumber('group');
    Route::get('students', [TeacherStudentController::class, 'index']);
    Route::get('students/{student}/summary', [TeacherStudentController::class, 'summary'])->whereNumber('student');
    Route::get('students/{student}/assessments', [TeacherStudentController::class, 'assessments'])->whereNumber('student');
    Route::get('students/{student}/assignments', [TeacherClassroomController::class, 'assignments'])->whereNumber('student');
    Route::get('quran/surahs', [TeacherClassroomController::class, 'surahs']);
    Route::get('sessions', [TeacherSessionController::class, 'index']);
    Route::get('sessions/{session}', [TeacherSessionController::class, 'show'])->whereNumber('session');
    Route::post('sessions', [TeacherSessionController::class, 'store']);
    Route::put('sessions/{session}', [TeacherSessionController::class, 'update'])->whereNumber('session');
});
