<?php

namespace App\Filament\Resources\QuranAssessments;

use App\Actions\SaveAssessment;
use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Filament\Schemas\AssessmentSchema;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use App\Models\StudentSession;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QuranAssessmentResource extends Resource
{
    protected static ?string $model = QuranAssessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

    protected static string|\UnitEnum|null $navigationGroup = 'التعليم';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([...AssessmentSchema::components()])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('assignment.student.name')->label('الطالب')->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->assignment->student_id])), TextEntry::make('studentSession.session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->url(fn ($record) => TeachingSessionResource::getUrl('view', ['record' => $record->studentSession->session_id])), TextEntry::make('assignment.surah_name')->label('الواجب')->url(fn ($record) => QuranAssignmentResource::getUrl('view', ['record' => $record->quran_assignment_id])), TextEntry::make('grade')->label('العلامة'), TextEntry::make('comment')->label('التعليق')->placeholder('—')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('assignment.student.name')->label('الطالب')->searchable()->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->assignment->student_id])), TextColumn::make('studentSession.session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->sortable()->url(fn ($record) => TeachingSessionResource::getUrl('view', ['record' => $record->studentSession->session_id])), TextColumn::make('assignment.surah_name')->label('السورة')->searchable()->url(fn ($record) => QuranAssignmentResource::getUrl('view', ['record' => $record->quran_assignment_id])), TextColumn::make('passage')->label('الآيات')->state(fn ($record) => $record->assignment->from_ayah.'–'.$record->assignment->to_ayah), TextColumn::make('grade')->label('العلامة')->sortable(), TextColumn::make('comment')->label('التعليق')->limit(40)->wrap()->searchable()])->filters([TrashedFilter::make(), SelectFilter::make('student_session_id')->label('سجل الطالب في الحصة')->options(fn () => StudentSession::withTrashed()->with(['student', 'session'])->get()->mapWithKeys(fn ($row) => [$row->id => $row->student->name.' · '.$row->session->timestamp->format('Y-m-d H:i').' · #'.$row->id]))->searchable(), SelectFilter::make('quran_assignment_id')->label('الواجب')->options(fn () => QuranAssignment::withTrashed()->with('student')->get()->mapWithKeys(fn ($row) => [$row->id => $row->student->name.' · '.$row->surah_name.' · #'.$row->id]))->searchable()])->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function saveRecord(array $data, ?QuranAssessment $record = null): Model
    {
        return app(SaveAssessment::class)->execute(StudentSession::withTrashed()->findOrFail($data['student_session_id'])->student, $data, $record);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListQuranAssessments::route('/'), 'create' => Pages\CreateQuranAssessment::route('/create'), 'edit' => Pages\EditQuranAssessment::route('/{record}/edit'), 'view' => Pages\ViewQuranAssessment::route('/{record}')];
    }
}
