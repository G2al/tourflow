<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InsuranceType: string implements HasLabel
{
    case MedicalLuggage = 'medical_luggage';
    case AllRisk = 'all_risk';
    case Cancellation = 'cancellation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::MedicalLuggage => 'Medico-bagaglio',
            self::AllRisk => 'All risk',
            self::Cancellation => 'Annullamento',
            self::Other => 'Altro',
        };
    }
}
