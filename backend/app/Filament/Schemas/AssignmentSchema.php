<?php

namespace App\Filament\Schemas;

use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Models\QuranAssignment;
use App\Support\QuranCatalog;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

class AssignmentSchema
{
    public static function originEntry(): TextEntry
    {
        return TextEntry::make('created_in_session_id')->label('حصة إنشاء الواجب')->placeholder('غير معروفة')
            ->formatStateUsing(fn ($state) => '#'.$state)
            ->url(fn (?QuranAssignment $record) => $record?->created_in_session_id ? TeachingSessionResource::getUrl('view', ['record' => $record->created_in_session_id]) : null);
    }

    public static function originColumn(): TextColumn
    {
        return TextColumn::make('created_in_session_id')->label('حصة الإنشاء')->placeholder('غير معروفة')->sortable()
            ->formatStateUsing(fn ($state) => '#'.$state)
            ->description(fn (QuranAssignment $record) => $record->createdInSession?->timestamp?->format('Y-m-d H:i'))
            ->url(fn (QuranAssignment $record) => $record->created_in_session_id ? TeachingSessionResource::getUrl('view', ['record' => $record->created_in_session_id]) : null);
    }

    public static function components(): array
    {
        return [
            self::originEntry()->visible(fn (?QuranAssignment $record) => $record !== null),
            Select::make('surah_name')->label('السورة')->options(fn (?QuranAssignment $record) => QuranCatalog::options() + ($record ? [$record->surah_name => $record->surah_name] : []))->searchable()->required()->live(),
            TextInput::make('from_ayah')->label('من الآية')->integer()->minValue(1)->required(),
            TextInput::make('to_ayah')->label('إلى الآية')->integer()->minValue(1)->gte('from_ayah')->required(),
            Select::make('type')->label('النوع')->options(['new_memorization' => 'حفظ جديد', 'revision' => 'مراجعة'])->default('new_memorization')->required(),
            Toggle::make('is_passed')->label('تم الإتقان')->default(false),
        ];
    }
}
