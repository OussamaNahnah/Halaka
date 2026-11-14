<?php

namespace App\Filament\Resources\TeachingSessions;

use App\Filament\RelationManagers\AssessmentsRelationManager;
use App\Filament\RelationManagers\StudentSessionsRelationManager;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingSession;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeachingSessionResource extends Resource
{
    protected static ?string $model = TeachingSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'حصة';

    protected static ?string $pluralModelLabel = 'الحصص';

    protected static string|\UnitEnum|null $navigationGroup = 'التعليم';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('teacher_id')->label('الأستاذ')->options(fn () => Teacher::withTrashed()->with('user')->get()->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->user->name.($teacher->trashed() ? ' (محذوف)' : '')]))->searchable()->required(),
            Select::make('group_id')->label('المجموعة')->options(fn () => Group::withTrashed()->get()->mapWithKeys(fn ($group) => [$group->id => $group->name.($group->trashed() ? ' (محذوف)' : '')]))->searchable(),
            DateTimePicker::make('timestamp')->label('التاريخ والوقت')->seconds(false)->default(now())->required(),
            Select::make('status')->label('الحالة')->options(['in_progress' => 'قيد التسجيل', 'completed' => 'مكتملة'])->default('in_progress')->required()->helperText('عند إكمال الحصة يسجل الطلاب غير المقيمين غائبين.'),
            Select::make('student_ids')->label('طلاب الحصة')->options(fn () => Student::query()->pluck('name', 'id'))->multiple()->searchable()->visibleOn('create')->dehydrated(fn (string $operation) => $operation === 'create')->columnSpanFull()->helperText('اختر الطلاب صراحة؛ تغيير المجموعة لاحقًا لا يغير سجل الحضور.'),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('timestamp')->label('التاريخ والوقت')->dateTime('Y-m-d H:i'),
            TextEntry::make('teacher.user.name')->label('الأستاذ'), TextEntry::make('group.name')->label('المجموعة')->placeholder('دون مجموعة'),
            TextEntry::make('status')->label('الحالة')->formatStateUsing(fn (string $state) => $state === 'completed' ? 'مكتملة' : 'قيد التسجيل')->badge(), ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('timestamp')->label('التاريخ والوقت')->dateTime('Y-m-d H:i')->sortable(),
            TextColumn::make('teacher.user.name')->label('الأستاذ')->searchable(),
            TextColumn::make('group.name')->label('المجموعة')->placeholder('دون مجموعة')->searchable(),
            TextColumn::make('status')->label('الحالة')->formatStateUsing(fn (string $state) => $state === 'completed' ? 'مكتملة' : 'قيد التسجيل')->badge()->color(fn (string $state) => $state === 'completed' ? 'success' : 'warning'),
            TextColumn::make('student_sessions_count')->label('عدد الطلاب')->counts('studentSessions'), ])->filters([TrashedFilter::make(), SelectFilter::make('group')->label('المجموعة')->relationship('group', 'name'),
                SelectFilter::make('teacher_id')->label('الأستاذ')->options(fn () => Teacher::with('user')->get()->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->user->name])),
                SelectFilter::make('status')->label('الحالة')->options(['in_progress' => 'قيد التسجيل', 'completed' => 'مكتملة']),
                Filter::make('date')->label('التاريخ')->schema([DatePicker::make('from')->label('من'), DatePicker::make('until')->label('إلى')])->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('timestamp', '>=', $date))->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('timestamp', '<=', $date))), ])->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make()])->toolbarActions([DeleteBulkAction::make(), RestoreBulkAction::make()])->defaultSort('timestamp', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [StudentSessionsRelationManager::class, AssessmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTeachingSessions::route('/'),
            'create' => Pages\CreateTeachingSession::route('/create'),
            'edit' => Pages\EditTeachingSession::route('/{record}/edit'),
            'view' => Pages\ViewTeachingSession::route('/{record}'), ];
    }
}
