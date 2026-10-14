<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Agency = 'agency';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore',
            self::Agency => 'Agenzia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Agency => 'info',
        };
    }
}
