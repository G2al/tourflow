<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Come viene venduto un prodotto all'interno di una pratica.
 */
enum BookingMode: string implements HasLabel
{
    case HotelOnly = 'hotel_only';
    case FlightHotel = 'flight_hotel';
    case Excursion = 'excursion';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::HotelOnly => 'Solo soggiorno',
            self::FlightHotel => 'Volo + hotel',
            self::Excursion => 'Escursione',
            self::Transfer => 'Transfer',
        };
    }

    public function isStay(): bool
    {
        return $this === self::HotelOnly || $this === self::FlightHotel;
    }
}
