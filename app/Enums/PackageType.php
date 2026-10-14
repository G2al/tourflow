<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PackageType: string implements HasColor, HasLabel
{
    case Stay = 'stay';
    case Transfer = 'transfer';
    case Excursion = 'excursion';

    /**
     * Etichetta usata anche dal frontend per i filtri (confronto esatto).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Stay => 'Soggiorno',
            self::Transfer => 'Transfer',
            self::Excursion => 'Escursione',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stay => 'info',
            self::Transfer => 'gray',
            self::Excursion => 'success',
        };
    }

    public function defaultPriceUnit(): PriceUnit
    {
        return $this === self::Transfer ? PriceUnit::PerService : PriceUnit::PerPerson;
    }

    /**
     * Accetta sia il valore ("stay") sia l'etichetta ("Soggiorno").
     */
    public static function fromInput(string $input): ?self
    {
        return self::tryFrom(strtolower($input))
            ?? collect(self::cases())->first(fn (self $case) => strcasecmp($case->getLabel(), $input) === 0);
    }
}
