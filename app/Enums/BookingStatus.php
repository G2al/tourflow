<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Quote = 'quote';
    case Option = 'option';
    case Confirmed = 'confirmed';
    case ProformaSent = 'proforma_sent';
    case ProformaViewed = 'proforma_viewed';
    case DepositReceived = 'deposit_received';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Quote => 'Preventivo',
            self::Option => 'Opzione',
            self::Confirmed => 'Confermata',
            self::ProformaSent => 'Proforma inviata',
            self::ProformaViewed => 'Proforma visualizzata',
            self::DepositReceived => 'Acconto ricevuto',
            self::Paid => 'Saldata',
            self::Expired => 'Scaduta',
            self::Cancelled => 'Annullata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Quote => 'gray',
            self::Option => 'warning',
            self::Confirmed, self::ProformaSent => 'info',
            self::ProformaViewed, self::DepositReceived => 'primary',
            self::Paid => 'success',
            self::Expired, self::Cancelled => 'danger',
        };
    }

    /**
     * Stati che occupano disponibilità (camere e posti volo). Un'opzione la
     * occupa solo finché non è scaduta.
     *
     * @return array<int, self>
     */
    public static function holdingInventory(): array
    {
        return [self::Option, self::Confirmed, self::ProformaSent, self::ProformaViewed, self::DepositReceived, self::Paid];
    }

    public function holdsInventory(): bool
    {
        return in_array($this, self::holdingInventory(), true);
    }

    /**
     * Pratiche concluse: non si possono più confermare o mettere in opzione.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }
}
