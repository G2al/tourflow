<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AgeCategory: string implements HasLabel
{
    case Adult = 'adult';
    case Child = 'child';
    case Infant = 'infant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adult => 'Adulto',
            self::Child => 'Bambino',
            self::Infant => 'Infant',
        };
    }
}
