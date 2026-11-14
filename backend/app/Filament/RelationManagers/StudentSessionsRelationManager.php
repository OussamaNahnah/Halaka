<?php

namespace App\Filament\RelationManagers;

use App\Actions\SaveStudentSession;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class StudentSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentSessions';

    protected static ?string $modelLabel = 'سجل طالب في حصة';

    protected static ?string $pluralModelLabel = 'طلاب الحصص';

    protected static ?string $title = 'سجل الحصص والطلاب';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $isStudent = $this->getOwnerRecord() instanceof Student;

        return $schema->components([
            Select::make($isStudent ? 'session_id' : 'student_id')->label($isStudent ? 'الحصة' : 'الطالب')->options(fn () => $isStudent
                ? TeachingSession::withTrashed()->with('group')->get()->mapWithKeys(fn ($session) => [$session->id => $session->timestamp->format('Y-m-d H:i').' · '.($session->group?->name ?? 'دون مجموعة').($session->trashed() ? ' (محذوف)' : '')])
                : Student::withTrashed()->get()->mapWithKeys(fn ($student) => [$student->id => $student->name.' · '.$student->id.($student->trashed() ? ' (محذوف)' : '')]))->searchable()->required()->live()->rule(fn (?StudentSession $record) => Rule::unique('student_sessions', $isStudent ? 'session_id' : 'student_id')->where($isStudent ? 'student_id' : 'session_id', $this->getOwnerRecord()->id)->ignore($record?->id)),
            Select::make('attendance_status')->label('الحضور')->options(function (Get $get) {
                $session = $this->getOwnerRecord() instanceof TeachingSession ? $this->getOwnerRecord() : TeachingSession::withTrashed()->find($get('session_id'));

                return ($session?->status === 'completed' ? [] : ['not_marked' => 'لم يُقيّم بعد']) + ['present' => 'حاضر', 'absent' => 'غائب'];
            })->default($this->getOwnerRecord() instanceof TeachingSession && $this->getOwnerRecord()->status === 'completed' ? 'absent' : 'not_marked')->required(),
            DateTimePicker::make('evaluated_at')->label('وقت إتمام الطالب')->seconds(false)->helperText('يحدد تلقائيًا للحاضر إن ترك فارغًا، ويمسح للغائب.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withTrashed()->with(['student', 'session.teacher.user', 'session.group']))
            ->columns([
                TextColumn::make('student.name')->label('الطالب')->searchable()->url(fn (StudentSession $record) => StudentResource::getUrl('view', ['record' => $record->student_id])),
                TextColumn::make('session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->sortable()->url(fn (StudentSession $record) => TeachingSessionResource::getUrl('view', ['record' => $record->session_id])),
                TextColumn::make('session.teacher.user.name')->label('الأستاذ'),
                TextColumn::make('session.group.name')->label('المجموعة')->placeholder('دون مجموعة'),
                TextColumn::make('attendance_status')->label('الحضور')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                    'present' => 'حاضر', 'absent' => 'غائب', default => 'لم يُقيّم بعد'
                })->color(fn (string $state) => match ($state) {
                    'present' => 'success', 'absent' => 'danger', default => 'gray'
                }),
                TextColumn::make('assessments_count')->label('التقييمات')->counts('assessments'),
            ])->defaultSort('id', 'desc')->emptyStateHeading('لا توجد سجلات حصص')->filters([TrashedFilter::make()])->headerActions([
                CreateAction::make()->using(fn (array $data) => app(SaveStudentSession::class)->execute($this->getOwnerRecord(), $data)),
            ])->recordActions([
                EditAction::make()->using(fn (StudentSession $record, array $data) => app(SaveStudentSession::class)->execute($this->getOwnerRecord(), $data, $record)), DeleteAction::make(), RestoreAction::make(),
            ])->toolbarActions([]);
    }
}
