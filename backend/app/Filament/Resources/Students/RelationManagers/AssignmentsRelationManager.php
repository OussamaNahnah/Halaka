<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Actions\SaveAssignment;
use App\Filament\Actions\AssessmentActions;
use App\Filament\Resources\TeachingSessions\TeachingSessionResource;
use App\Filament\Schemas\AssignmentSchema;
use App\Models\QuranAssessment;
use App\Models\QuranAssignment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $modelLabel = 'واجب';

    protected static ?string $pluralModelLabel = 'الواجبات';

    protected static ?string $title = 'الواجبات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(AssignmentSchema::components());
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withTrashed())->columns([
            TextColumn::make('surah_name')->label('السورة')->searchable(),
            TextColumn::make('passage')->label('الآيات')->state(fn (QuranAssignment $record) => $record->from_ayah.'–'.$record->to_ayah),
            TextColumn::make('type')->label('النوع')->formatStateUsing(fn (string $state) => $state === 'revision' ? 'مراجعة' : 'حفظ جديد'),
            TextColumn::make('is_passed')->label('الحالة')->badge()->formatStateUsing(fn (bool $state) => $state ? 'مجتاز' : 'قيد الإنجاز')->color(fn (bool $state) => $state ? 'success' : 'warning'),
            TextColumn::make('assessments_count')->label('المحاولات')->counts('assessments'),
        ])->filters([TrashedFilter::make(),
            TernaryFilter::make('is_passed')->label('الحالة')->trueLabel('مجتاز')->falseLabel('قيد الإنجاز'),
            SelectFilter::make('type')->label('النوع')->options(['new_memorization' => 'حفظ جديد', 'revision' => 'مراجعة']),
        ])->headerActions([CreateAction::make()->using(fn (array $data) => app(SaveAssignment::class)->execute($this->getOwnerRecord(), $data))])->recordActions([
            EditAction::make()->using(fn (QuranAssignment $record, array $data) => app(SaveAssignment::class)->execute($this->getOwnerRecord(), $data, $record)), DeleteAction::make(), RestoreAction::make(),
            ViewAction::make()->label('عرض التقييمات')->modalHeading('الواجب ومحاولات التسميع')->schema([
                TextEntry::make('surah_name')->label('السورة'),
                TextEntry::make('from_ayah')->label('من الآية'), TextEntry::make('to_ayah')->label('إلى الآية'),
                RepeatableEntry::make('assessments')->label('التقييمات')->schema([
                    TextEntry::make('grade')->label('العلامة'), TextEntry::make('comment')->label('التعليق')->placeholder('—'),
                    TextEntry::make('studentSession.session.timestamp')->label('الحصة')->dateTime('Y-m-d H:i')->url(fn (QuranAssessment $record) => TeachingSessionResource::getUrl('view', ['record' => $record->studentSession->session_id])),
                    TextEntry::make('studentSession.session.teacher.user.name')->label('الأستاذ'),
                    Actions::make([AssessmentActions::correct()]),
                ])->columns(2),
            ]),
        ])->toolbarActions([])->defaultSort('id', 'desc')->emptyStateHeading('لا توجد واجبات مسجلة');
    }
}
