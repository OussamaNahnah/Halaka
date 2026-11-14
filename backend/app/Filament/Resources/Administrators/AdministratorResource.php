<?php

namespace App\Filament\Resources\Administrators;

use App\Models\User;
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

class AdministratorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'administrators';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'مشرف';

    protected static ?string $pluralModelLabel = 'المشرفون';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(255)->rule(fn (?User $record) => Rule::unique('users', 'email')->ignore($record?->id)),
            TextInput::make('password')->label('كلمة المرور')->password()->revealable()->autocomplete('new-password')->minLength(8)->maxLength(255)->required(fn (string $operation) => $operation === 'create')->dehydrated(fn (?string $state) => filled($state))->helperText(fn (string $operation) => $operation === 'edit' ? 'اتركها فارغة للحفاظ على كلمة المرور الحالية.' : null),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('الاسم')->searchable()->sortable(),
            TextColumn::make('email')->label('البريد الإلكتروني')->searchable()->sortable(),
            TextColumn::make('current_account')->label('الحساب')->state(fn (User $record) => $record->is(auth()->user()) ? 'حسابك الحالي' : '')->badge(),
        ])->filters([TrashedFilter::make()])->recordActions([
            EditAction::make(), DeleteAction::make(), RestoreAction::make(),
        ])->toolbarActions([
            DeleteBulkAction::make()->authorizeIndividualRecords(),
            RestoreBulkAction::make()->authorizeIndividualRecords(),
        ])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->whereHas('roles', fn (Builder $query) => $query->where('name', 'Administrator'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdministrators::route('/'),
            'create' => Pages\CreateAdministrator::route('/create'),
            'edit' => Pages\EditAdministrator::route('/{record}/edit'),
        ];
    }
}
