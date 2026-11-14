<?php

namespace App\Filament\Resources\Teachers;

use App\Models\Teacher;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'أستاذ';

    protected static ?string $pluralModelLabel = 'الأساتذة';

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(255)->rule(fn (?Teacher $record) => Rule::unique('users', 'email')->ignore($record?->user_id)),
            TextInput::make('password')->label('كلمة المرور')->password()->revealable()->autocomplete('new-password')->minLength(8)->maxLength(255)->required(fn (string $operation) => $operation === 'create')->dehydrated(fn (?string $state) => filled($state))->helperText(fn (string $operation) => $operation === 'edit' ? 'اتركها فارغة للحفاظ على كلمة المرور الحالية.' : null), ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('user.name')->label('الاسم')->searchable()->sortable(),
            TextColumn::make('user.email')->label('البريد الإلكتروني')->searchable()->sortable(),
            TextColumn::make('sessions_count')->label('الحصص')->counts('sessions'), ])->filters([TrashedFilter::make()])->recordActions([DeleteAction::make(), RestoreAction::make(), EditAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('id', 'desc');
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
        return ['index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'), 'edit' => Pages\EditTeacher::route('/{record}/edit'), ];
    }
}
