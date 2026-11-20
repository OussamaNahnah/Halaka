<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\QuranAssignments\QuranAssignmentResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\QuranAssignment;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CreatedAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'createdAssignments';

    protected static ?string $title = 'الواجبات المعطاة في هذه الحصة';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withTrashed()->with('student'))->columns([
            TextColumn::make('student.name')->label('الطالب')->searchable()->url(fn (QuranAssignment $record) => StudentResource::getUrl('view', ['record' => $record->student_id])),
            TextColumn::make('surah_name')->label('السورة')->searchable(),
            TextColumn::make('passage')->label('الآيات')->state(fn (QuranAssignment $record) => $record->from_ayah.'–'.$record->to_ayah),
            TextColumn::make('type')->label('النوع')->formatStateUsing(fn (string $state) => $state === 'revision' ? 'مراجعة' : 'حفظ جديد'),
            TextColumn::make('is_passed')->label('الإتقان الحالي')->badge()->formatStateUsing(fn (bool $state) => $state ? 'مجتاز' : 'قيد الإنجاز'),
            TextColumn::make('deleted_at')->label('الأرشفة')->dateTime('Y-m-d H:i')->placeholder('فعال'),
        ])->filters([TrashedFilter::make()])->recordActions([
            ViewAction::make()->url(fn (QuranAssignment $record) => QuranAssignmentResource::getUrl('view', ['record' => $record->id])),
        ])->defaultSort('id', 'desc')->emptyStateHeading('لا توجد واجبات مرتبطة بهذه الحصة');
    }
}
