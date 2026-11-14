<?php

namespace App\Filament\Schemas;

use App\Models\QuranAssignment;
use App\Support\QuranCatalog;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

class AssignmentSchema
{
    public static function components(): array
    {
        return [
            Select::make('surah_name')->label('السورة')->options(fn (?QuranAssignment $record) => QuranCatalog::options() + ($record ? [$record->surah_name => $record->surah_name] : []))->searchable()->required()->live(),
            TextInput::make('from_ayah')->label('من الآية')->integer()->minValue(1)->required(),
            TextInput::make('to_ayah')->label('إلى الآية')->integer()->minValue(1)->gte('from_ayah')->maxValue(fn (Get $get) => QuranCatalog::verseCount($get('surah_name') ?? ''))->required(),
            Select::make('type')->label('النوع')->options(['new_memorization' => 'حفظ جديد', 'revision' => 'مراجعة'])->default('new_memorization')->required(),
            Toggle::make('is_passed')->label('تم الإتقان')->default(false),
        ];
    }
}
