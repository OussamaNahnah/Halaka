<?php

namespace App\Support;

class QuranCatalog
{
    public static function options(): array
    {
        return collect(config('quran'))->mapWithKeys(fn (array $surah) => [$surah['name'] => $surah['name']])->all();
    }

    public static function arabicName(string $name): ?string
    {
        if ($name === 'Al-Fatiha') {
            return 'الفاتحة';
        }
        $surah = collect(config('quran'))->first(fn (array $surah) => in_array($name, [$surah['identifier'], $surah['name']], true));

        return $surah['name'] ?? null;
    }

    public static function verseCount(string $name): ?int
    {
        $arabic = self::arabicName($name);
        $surah = collect(config('quran'))->first(fn (array $surah) => $surah['name'] === $arabic);

        return $surah['verses'] ?? null;
    }
}
