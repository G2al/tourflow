<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceUnit: string implements HasLabel
{
    case PerPerson = 'per_person';
    case PerService = 'per_service';

    public function getLabel(): string
    {
        return match ($this) {
            self::PerPerson => 'Per persona',
            self::PerService => 'A servizio',
        };
    }
}
