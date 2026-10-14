<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InsuranceType: string implements HasLabel
{
    case MedicalLuggage = 'medical_luggage';
    case Cancellation = 'cancellation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::MedicalLuggage => 'Medico-bagaglio',
            self::Cancellation => 'Annullamento',
            self::Other => 'Altro',
        };
    }
}
