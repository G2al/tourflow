<?php

namespace App\Support;

use App\Enums\AgeCategory;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Fasce d'età globali (impostazioni): di default infant sotto i 2 anni,
 * bambino 2-17, adulto da 18. L'età è quella compiuta alla data del servizio.
 */
class AgePolicy
{
    public static function ageAt(CarbonInterface $birthDate, CarbonInterface $date): int
    {
        return (int) $birthDate->diffInYears($date);
    }

    public static function categoryFor(int $age): AgeCategory
    {
        return match (true) {
            $age <= Setting::int('infant_max_age') => AgeCategory::Infant,
            $age <= Setting::int('child_max_age') => AgeCategory::Child,
            default => AgeCategory::Adult,
        };
    }

    /**
     * @return array<string, array{min: int, max: int|null}>
     */
    public static function ranges(): array
    {
        $infant = Setting::int('infant_max_age');
        $child = Setting::int('child_max_age');

        return [
            'infant' => ['min' => 0, 'max' => $infant],
            'child' => ['min' => $infant + 1, 'max' => $child],
            'adult' => ['min' => $child + 1, 'max' => null],
        ];
    }
}
