<?php

namespace App\Filament\Resources\QuranAssignments;

use App\Actions\SaveAssignment;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Schemas\AssignmentSchema;
use App\Models\QuranAssignment;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QuranAssignmentResource extends Resource
{
    protected static ?string $model = QuranAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'واجب';

    protected static ?string $pluralModelLabel = 'الواجبات';

    protected static string|\UnitEnum|null $navigationGroup = 'التعليم';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('student_id')->label('الطالب')->options(fn () => Student::withTrashed()->get()->mapWithKeys(fn ($student) => [$student->id => $student->name.' · #'.$student->id.($student->trashed() ? ' (محذوف)' : '')]))->searchable()->required()->exists('students', 'id'), ...AssignmentSchema::components()])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('student.name')->label('الطالب')->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->student_id])), TextEntry::make('surah_name')->label('السورة'), TextEntry::make('from_ayah')->label('من الآية'), TextEntry::make('to_ayah')->label('إلى الآية'), TextEntry::make('is_passed')->label('الإتقان')->formatStateUsing(fn ($state) => $state ? 'مجتاز' : 'قيد الإنجاز')])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('student.name')->label('الطالب')->searchable()->url(fn ($record) => StudentResource::getUrl('view', ['record' => $record->student_id])), TextColumn::make('surah_name')->label('السورة')->searchable(), TextColumn::make('passage')->label('الآيات')->state(fn ($record) => $record->from_ayah.'–'.$record->to_ayah), TextColumn::make('type')->label('النوع')->formatStateUsing(fn ($state) => $state === 'revision' ? 'مراجعة' : 'حفظ جديد'), TextColumn::make('is_passed')->label('الإتقان')->badge()->formatStateUsing(fn ($state) => $state ? 'مجتاز' : 'قيد الإنجاز'), TextColumn::make('assessments_count')->label('المحاولات')->counts('assessments')])->filters([TrashedFilter::make(), SelectFilter::make('student_id')->label('الطالب')->relationship('student', 'name')->searchable(), TernaryFilter::make('is_passed')->label('الإتقان'), SelectFilter::make('type')->label('النوع')->options(['revision' => 'مراجعة', 'new_memorization' => 'حفظ جديد'])])->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function saveRecord(array $data, ?QuranAssignment $record = null): Model
    {
        return app(SaveAssignment::class)->execute(Student::withTrashed()->findOrFail($data['student_id']), $data, $record, allowStudentChange: true);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListQuranAssignments::route('/'), 'create' => Pages\CreateQuranAssignment::route('/create'), 'edit' => Pages\EditQuranAssignment::route('/{record}/edit'), 'view' => Pages\ViewQuranAssignment::route('/{record}')];
    }
}
