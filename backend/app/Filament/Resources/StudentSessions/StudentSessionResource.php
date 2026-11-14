<?php

namespace App\Filament\Resources\StudentSessions;

use App\Actions\SaveStudentSession;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Models\Student;
use App\Models\StudentSession;
use App\Models\TeachingSession;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentSessionResource extends Resource
{
    protected static ?string $model = StudentSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'سجل طالب في حصة';

    protected static ?string $pluralModelLabel = 'طلاب الحصص';

    protected static string|\UnitEnum|null $navigationGroup = 'التعليم';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('student_id')->label('الطالب')->options(fn () => Student::withTrashed()->get()->mapWithKeys(fn ($student) => [$student->id => $student->name.' · #'.$student->id.($student->trashed() ? ' (محذوف)' : '')]))->searchable()->required()->exists('students', 'id'),
            Select::make('session_id')->label('الحصة')->options(fn () => TeachingSession::withTrashed()->with(['teacher.user', 'group'])->latest('timestamp')->get()->mapWithKeys(fn ($session) => [$session->id => '#'.$session->id.' · '.$session->timestamp->format('Y-m-d H:i').' · '.$session->teacher->user->name.' · '.($session->group?->name ?? 'دون مجموعة').($session->trashed() ? ' (محذوف)' : '')]))->searchable()->required()->exists('teaching_sessions', 'id')->live(),
            Select::make('attendance_status')->label('الحضور')->options(fn (Get $get) => (TeachingSession::withTrashed()->find($get('session_id'))?->status === 'completed' ? [] : ['not_marked' => 'لم يُقيّم بعد']) + ['present' => 'حاضر', 'absent' => 'غائب'])->default('not_marked')->required(),
            DateTimePicker::make('evaluated_at')->label('وقت إتمام الطالب')->seconds(false)->helperText('يحدد تلقائيًا للحاضر إن ترك فارغًا، ويمسح للغائب.')])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('student.name')->label('الطالب')->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->student_id])), TextEntry::make('session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->url(fn ($record) => TeachingSessionResource::getUrl('view', ['record' => $record->session_id])), TextEntry::make('attendance_status')->label('الحضور')->formatStateUsing(fn ($state) => match ($state) {
            'present' => 'حاضر','absent' => 'غائب',default => 'لم يُقيّم بعد'
        }), TextEntry::make('evaluated_at')->label('وقت الإتمام')->dateTime('Y-m-d H:i')->placeholder('—')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('student.name')->label('الطالب')->searchable()->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->student_id])),
            TextColumn::make('session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->sortable()->url(fn ($record) => TeachingSessionResource::getUrl('view', ['record' => $record->session_id])),
            TextColumn::make('session.teacher.user.name')->label('الأستاذ')->searchable(),
            TextColumn::make('session.group.name')->label('المجموعة')->searchable(),
            TextColumn::make('attendance_status')->label('الحضور')->badge()->formatStateUsing(fn ($state) => match ($state) {
                'present' => 'حاضر','absent' => 'غائب',default => 'لم يُقيّم بعد'
            }),
            TextColumn::make('assessments_count')->label('التقييمات')->counts('assessments')])->filters([TrashedFilter::make(), SelectFilter::make('student_id')->label('الطالب')->relationship('student', 'name')->searchable(), SelectFilter::make('attendance_status')->label('الحضور')->options(['present' => 'حاضر', 'absent' => 'غائب', 'not_marked' => 'لم يُقيّم بعد'])])->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function saveRecord(array $data, ?StudentSession $record = null): Model
    {
        return app(SaveStudentSession::class)->execute(Student::withTrashed()->findOrFail($data['student_id']), $data, $record);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStudentSessions::route('/'), 'create' => Pages\CreateStudentSession::route('/create'), 'edit' => Pages\EditStudentSession::route('/{record}/edit'), 'view' => Pages\ViewStudentSession::route('/{record}')];
    }
}
