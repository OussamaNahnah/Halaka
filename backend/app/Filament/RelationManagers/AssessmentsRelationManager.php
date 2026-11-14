<?php

namespace App\Filament\RelationManagers;

use App\Actions\SaveAssessment;
use App\Filament\Actions\AssessmentActions;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Filament\Schemas\AssessmentSchema;
use App\Models\QuranAssessment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessments';

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

    protected static ?string $title = 'التقييمات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AssessmentSchema::components($this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withTrashed()->with(['assignment.student', 'studentSession.session.teacher.user']))
            ->columns([
                TextColumn::make('assignment.student.name')->label('الطالب')->url(fn (QuranAssessment $record) => StudentResource::getUrl('view', ['record' => $record->assignment->student_id])),
                TextColumn::make('assignment.surah_name')->label('السورة')->searchable(),
                TextColumn::make('passage')->label('الآيات')->state(fn (QuranAssessment $record) => $record->assignment->from_ayah.'–'.$record->assignment->to_ayah),
                TextColumn::make('assignment.type')->label('النوع')->formatStateUsing(fn (string $state) => $state === 'revision' ? 'مراجعة' : 'حفظ جديد'),
                TextColumn::make('grade')->label('العلامة')->sortable(),
                TextColumn::make('comment')->label('التعليق')->wrap()->placeholder('—'),
                TextColumn::make('studentSession.session.timestamp')->label('حصة التقييم')->dateTime('Y-m-d H:i')->url(fn (QuranAssessment $record) => TeachingSessionResource::getUrl('view', ['record' => $record->studentSession->session_id])),
                TextColumn::make('studentSession.session.teacher.user.name')->label('الأستاذ'),
            ])
            ->filters([TrashedFilter::make(), SelectFilter::make('type')->label('النوع')->options(['new_memorization' => 'حفظ جديد', 'revision' => 'مراجعة'])->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $type) => $query->whereHas('assignment', fn ($query) => $query->where('type', $type))))])
            ->headerActions([CreateAction::make()->using(fn (array $data) => app(SaveAssessment::class)->execute($this->getOwnerRecord(), $data))])
            ->recordActions([AssessmentActions::correct(), EditAction::make()->using(fn (QuranAssessment $record, array $data) => app(SaveAssessment::class)->execute($this->getOwnerRecord(), $data, $record)), DeleteAction::make(), RestoreAction::make()])->toolbarActions([])->defaultSort('id', 'desc')->emptyStateHeading('لا توجد تقييمات مسجلة');
    }
}
