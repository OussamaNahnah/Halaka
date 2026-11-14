<?php

namespace App\Support;

class QuranCatalog
{
    public static function options(): array
    {
        $options = collect(config('quran'))->mapWithKeys(fn (array $surah) => [$surah['identifier'] => $surah['name']])->all();

        return $options + ['Al-Fatiha' => 'الفاتحة'];
    }

    public static function verseCount(string $name): ?int
    {
        if ($name === 'Al-Fatiha') {
            return 7;
        }
        $surah = collect(config('quran'))->first(fn (array $surah) => in_array($name, [$surah['identifier'], $surah['name']], true));

        return $surah['verses'] ?? null;
    }
}
