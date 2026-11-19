<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Support\QuranCatalog;
use App\Support\SchoolSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuranCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_114_surahs_have_unique_arabic_options_and_correct_total_ayah_count(): void
    {
        $this->assertCount(114, config('quran'));
        $this->assertCount(114, QuranCatalog::options());
        $this->assertSame(6236, array_sum(array_column(config('quran'), 'verses')));
        $this->assertSame('الفاتحة', array_key_first(QuranCatalog::options()));
        $this->assertSame('الناس', array_key_last(QuranCatalog::options()));
        foreach (config('quran') as $surah) {
            $this->assertSame($surah['name'], QuranCatalog::arabicName($surah['identifier']));
            $this->assertSame($surah['verses'], QuranCatalog::verseCount($surah['name']));
            $this->assertSame($surah['name'], QuranCatalog::options()[$surah['name']]);
        }
    }

    public function test_assignment_model_stores_arabic_for_arabic_or_legacy_input(): void
    {
        $student = Student::create(['name' => 'Student']);
        foreach (['Al-Mulk' => 'الملك', 'Al-Fatiha' => 'الفاتحة', 'الناس' => 'الناس'] as $input => $arabic) {
            $record = $student->assignments()->create(['surah_name' => $input, 'from_ayah' => 1, 'to_ayah' => 3, 'type' => 'revision']);
            $this->assertDatabaseHas('quran_assignments', ['id' => $record->id, 'surah_name' => $arabic]);
        }
    }

    public function test_normalization_migration_preserves_ids_passed_state_and_archived_records(): void
    {
        $student = Student::create(['name' => 'Student']);
        $record = $student->assignments()->create(['surah_name' => 'Al-Mulk', 'from_ayah' => 1, 'to_ayah' => 10, 'type' => 'revision', 'is_passed' => true]);
        $record->delete();
        DB::table('quran_assignments')->where('id', $record->id)->update(['surah_name' => 'Al-Mulk']);
        $revision = SchoolSync::revision();
        $migration = require database_path('migrations/2026_11_14_300002_store_surah_names_in_arabic.php');
        $migration->up();
        $this->assertSame($revision + 1, SchoolSync::revision());
        $this->assertDatabaseHas('quran_assignments', ['id' => $record->id, 'surah_name' => 'الملك', 'is_passed' => true, 'from_ayah' => 1, 'to_ayah' => 10]);
        $this->assertSoftDeleted($record);
        $migration->up();
        $this->assertSame($revision + 1, SchoolSync::revision());
    }
}
