<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case IdentityCard = 'identity_card';
    case Passport = 'passport';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdentityCard => "Carta d'identità",
            self::Passport => 'Passaporto',
        };
    }
}
