<?php

namespace App\Enums;

/**
 * Scelta finale del carrello (o passaggio successivo di una pratica).
 */
enum BookingAction: string
{
    /** Preventivo: nessun blocco di disponibilità. */
    case Quote = 'quote';

    /** Opzione: blocca la disponibilità per un tempo limitato. */
    case Option = 'option';

    /** Conferma: blocca la disponibilità e avvia il pagamento con bonifico. */
    case Confirm = 'confirm';

    public function holdsInventory(): bool
    {
        return $this !== self::Quote;
    }
}
