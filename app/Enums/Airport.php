<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Aeroporti di partenza dei voli acquistati dal tour operator.
 */
enum Airport: string implements HasLabel
{
    case Naples = 'NAP';
    case Rome = 'FCO';
    case Milan = 'MXP';
    case Bari = 'BRI';

    public function getLabel(): string
    {
        return match ($this) {
            self::Naples => 'Napoli',
            self::Rome => 'Roma Fiumicino',
            self::Milan => 'Milano Malpensa',
            self::Bari => 'Bari',
        };
    }

    public function city(): string
    {
        return match ($this) {
            self::Naples => 'Napoli',
            self::Rome => 'Roma',
            self::Milan => 'Milano',
            self::Bari => 'Bari',
        };
    }
}
