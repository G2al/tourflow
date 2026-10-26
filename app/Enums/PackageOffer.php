<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Cosa si sta vendendo con un pacchetto. Determina tipo di prodotto,
 * modalità di prenotazione e campi richiesti nel pannello.
 */
enum PackageOffer: string implements HasDescription, HasIcon, HasLabel
{
    case HotelOnly = 'hotel_only';
    case FlightHotel = 'flight_hotel';
    case FullPackage = 'full_package';
    case Transfer = 'transfer';
    case Excursion = 'excursion';

    /**
     * Etichetta mostrata anche sul sito (campo "type" del catalogo).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::HotelOnly => 'Solo soggiorno',
            self::FlightHotel => 'Volo + hotel',
            self::FullPackage => 'Pacchetto completo',
            self::Transfer => 'Transfer',
            self::Excursion => 'Escursione',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::HotelOnly => 'Hotel con date libere. Volo facoltativo attivabile.',
            self::FlightHotel => 'Hotel + volo a date fisse: le notti sono quelle del volo.',
            self::FullPackage => 'Volo + hotel + transfer, tutto incluso.',
            self::Transfer => 'Trasferimento con prezzo unico per il gruppo.',
            self::Excursion => 'Escursione in giornata con prezzo a persona.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::HotelOnly => 'heroicon-o-building-office-2',
            self::FlightHotel => 'heroicon-o-paper-airplane',
            self::FullPackage => 'heroicon-o-gift',
            self::Transfer => 'heroicon-o-truck',
            self::Excursion => 'heroicon-o-sun',
        };
    }

    public function packageType(): PackageType
    {
        return match ($this) {
            self::Transfer => PackageType::Transfer,
            self::Excursion => PackageType::Excursion,
            default => PackageType::Stay,
        };
    }

    public function isStay(): bool
    {
        return $this->packageType() === PackageType::Stay;
    }

    /**
     * Il volo è sempre compreso (non facoltativo).
     */
    public function includesFlight(): bool
    {
        return $this === self::FlightHotel || $this === self::FullPackage;
    }

    /**
     * Valori accettati dai filtri: chiave ("full_package"), etichetta
     * ("Pacchetto completo") o "Soggiorno" per tutte le offerte con hotel.
     *
     * @return array<int, self>
     */
    public static function fromFilter(string $input): array
    {
        if (strcasecmp($input, 'Soggiorno') === 0 || strcasecmp($input, 'stay') === 0) {
            return array_values(array_filter(self::cases(), fn (self $offer) => $offer->isStay()));
        }

        $offer = self::tryFrom(strtolower($input))
            ?? collect(self::cases())->first(fn (self $case) => strcasecmp($case->getLabel(), $input) === 0);

        return $offer ? [$offer] : [];
    }
}
