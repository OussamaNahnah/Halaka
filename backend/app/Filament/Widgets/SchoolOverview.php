<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Groups\GroupResource;
use App\Filament\Resources\QuranAssessments\QuranAssessmentResource;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\StudentSessions\StudentSessionResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Models\Group;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\Teacher;
use App\Models\TeachingSession;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SchoolOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('المشرفون', User::whereHas('roles', fn ($query) => $query->where('name', 'Administrator'))->count())->url(AdministratorResource::getUrl())->icon('heroicon-o-shield-check')->color('primary'),
            Stat::make('الطلاب', Student::count())->url(StudentResource::getUrl())->icon('heroicon-o-user-group')->color('primary'),
            Stat::make('المجموعات', Group::count())->url(GroupResource::getUrl())->icon('heroicon-o-rectangle-group')->color('primary'),
            Stat::make('الأساتذة', Teacher::count())->url(TeacherResource::getUrl())->icon('heroicon-o-academic-cap')->color('primary'),
            Stat::make('طلاب الحصص', StudentSession::count())->url(StudentSessionResource::getUrl())->icon('heroicon-o-clipboard-document-list')->color('primary'),
            Stat::make('الواجبات', QuranAssignment::count())->url(QuranAssignmentResource::getUrl())->icon('heroicon-o-book-open')->color('primary'),
            Stat::make('التقييمات', QuranAssessment::count())->url(QuranAssessmentResource::getUrl())->icon('heroicon-o-pencil-square')->color('primary'),
            Stat::make('الحصص', TeachingSession::count())->url(TeachingSessionResource::getUrl())->icon('heroicon-o-calendar-days')->color('primary'),
        ];
    }
}
