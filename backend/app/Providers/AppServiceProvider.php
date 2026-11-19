<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Models\User;
use App\Observers\SchoolSyncObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PHPOpenSourceSaver\JWTAuth\Http\Parser\AuthHeaders;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([User::class, Teacher::class, Student::class, Group::class, GroupStudent::class, TeachingSession::class, StudentSession::class, QuranAssignment::class, QuranAssessment::class] as $model) {
            $model::observe(SchoolSyncObserver::class);
        }
        $this->app->afterResolving('tymon.jwt.parser', fn ($parser) => $parser->setChain([new AuthHeaders]));
        RateLimiter::for('teacher-login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('teacher-api', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id.'|'.$request->ip()));
    }
}
