<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;

class WhatsAppLink
{
    /**
     * Link wa.me verso il numero del tour operator con il riepilogo della
     * prenotazione già compilato. Null se il numero non è configurato.
     */
    public static function forBooking(Booking $booking): ?string
    {
        $number = preg_replace('/\D+/', '', (string) Setting::get('whatsapp_number'));

        if ($number === '') {
            return null;
        }

        return "https://wa.me/{$number}?text=".rawurlencode(self::message($booking));
    }

    public static function message(Booking $booking): string
    {
        $package = $booking->package;
        $participants = $booking->participants;

        $lines = [
            "Ciao! Vorrei confermare la richiesta di prenotazione *{$booking->code}*.",
            '',
            "*Pacchetto:* {$package->title}",
            "*Destinazione:* {$package->destination->name}",
        ];

        if ($package->hotel) {
            $lines[] = "*Hotel:* {$package->hotel->name}";
        }

        $lines[] = "*Date:* dal {$booking->departure_date->format('d/m/Y')} al {$booking->return_date->format('d/m/Y')} ({$package->nights} notti)";
        $lines[] = "*Partecipanti:* {$participants->count()}";

        foreach ($participants as $participant) {
            $lines[] = "- {$participant->first_name} {$participant->last_name} ({$participant->age_category->getLabel()})";
        }

        if ($booking->extras->isNotEmpty()) {
            $lines[] = '*Extra:* '.$booking->extras->pluck('name')->join(', ');
        }

        $lines[] = '*Totale:* € '.number_format((float) $booking->total_amount, 2, ',', '.').($booking->isNetPrice() ? ' (netto agenzia)' : '');

        if ($booking->agency) {
            $lines[] = '';
            $lines[] = "*Agenzia:* {$booking->agency->company_name} - P.IVA {$booking->agency->vat_number}";
            if ($booking->agency_reference) {
                $lines[] = "*Rif. pratica:* {$booking->agency_reference}";
            }
        }

        $lines[] = '';
        $lines[] = "*Referente:* {$booking->contactFullName()} - {$booking->contact_phone}";

        return implode("\n", $lines);
    }
}
