<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Male = 'M';
    case Female = 'F';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Uomo',
            self::Female => 'Donna',
        };
    }
}
