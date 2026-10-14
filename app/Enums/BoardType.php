<?php

namespace App\Enums;

/**
 * Formule suggerite nel pannello. Il campo "board" del pacchetto è testo
 * libero: queste sono solo le voci proposte.
 */
enum BoardType: string
{
    case RoomOnly = 'Solo pernottamento';
    case BedAndBreakfast = 'Pernottamento e colazione';
    case HalfBoard = 'Mezza pensione';
    case FullBoard = 'Pensione completa';
    case AllInclusive = 'All Inclusive';
    case LunchIncluded = 'Pranzo incluso';
    case PrivateService = 'Servizio privato';

    /**
     * @return array<int, string>
     */
    public static function suggestions(): array
    {
        return array_column(self::cases(), 'value');
    }
}
