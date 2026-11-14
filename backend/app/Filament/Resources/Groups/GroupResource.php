<?php

namespace App\Filament\Resources\Groups;

use App\Models\Group;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $modelLabel = 'مجموعة';

    protected static ?string $pluralModelLabel = 'المجموعات';

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('اسم المجموعة')->required()->maxLength(255),
            Select::make('students')->label('الطلاب')->relationship('students', 'name', modifyQueryUsing: fn ($query) => $query->withTrashed())->getOptionLabelFromRecordUsing(fn (Student $record) => $record->name.' · '.$record->id.($record->trashed() ? ' (محذوف)' : ''))->multiple()->searchable()->preload()->columnSpanFull(), ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('المجموعة')->searchable()->sortable(),
            TextColumn::make('students_count')->label('عدد الطلاب')->counts('students')->sortable(), ])->filters([TrashedFilter::make()])->recordActions([DeleteAction::make(), RestoreAction::make(), EditAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'), 'edit' => Pages\EditGroup::route('/{record}/edit'), ];
    }
}
