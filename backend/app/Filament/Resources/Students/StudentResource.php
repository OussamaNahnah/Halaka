<?php

namespace App\Filament\Resources\Students;

use App\Filament\RelationManagers\AssessmentsRelationManager;
use App\Filament\RelationManagers\StudentSessionsRelationManager;
use App\Filament\Resources\Students\RelationManagers\AssignmentsRelationManager;
use App\Models\Group;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            TextInput::make('parent_phone')->label('هاتف ولي الأمر')->tel()->maxLength(255),
            Select::make('groups')->label('المجموعات')->relationship('groups', 'name', modifyQueryUsing: fn ($query) => $query->withTrashed())->getOptionLabelFromRecordUsing(fn (Group $record) => $record->name.($record->trashed() ? ' (محذوف)' : ''))->multiple()->searchable()->preload()->columnSpanFull(), ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('id')->label('رقم الطالب'), TextEntry::make('name')->label('الاسم'),
            TextEntry::make('parent_phone')->label('هاتف ولي الأمر')->placeholder('—'), TextEntry::make('groups.name')->label('المجموعات')->badge(), ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label('الرقم')->sortable()->searchable(),
            TextColumn::make('name')->label('الاسم')->sortable()->searchable(),
            TextColumn::make('parent_phone')->label('هاتف ولي الأمر')->searchable()->placeholder('—'),
            TextColumn::make('groups.name')->label('المجموعات')->badge()->searchable(), ])->filters([TrashedFilter::make(), SelectFilter::make('groups')->label('المجموعة')->relationship('groups', 'name')->searchable()->preload()])->recordActions([DeleteAction::make(), RestoreAction::make(), ViewAction::make(), EditAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [StudentSessionsRelationManager::class, AssignmentsRelationManager::class, AssessmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'), 'edit' => Pages\EditStudent::route('/{record}/edit'),
            'view' => Pages\ViewStudent::route('/{record}'), ];
    }
}
