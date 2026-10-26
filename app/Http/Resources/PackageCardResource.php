<?php

namespace App\Http\Resources;

use App\Enums\BookingMode;
use App\Enums\PackageOffer;
use App\Http\Resources\Concerns\ExposesPrices;
use App\Models\Package;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pacchetto nel formato del frontend (contratto TravelPackage), usato
 * nell'elenco. I campi dopo "itinerary" sono aggiunte del backend.
 *
 * @mixin Package
 */
class PackageCardResource extends JsonResource
{
    use ExposesPrices;

    public function toArray(Request $request): array
    {
        $price = $this->startingPrice();

        return [
            // Contratto TravelPackage
            'slug' => $this->slug,
            'title' => $this->title,
            'destination' => $this->destination->name,
            'country' => $this->destination->country,
            'location' => $this->location ?? $this->destination->name,
            'image' => Media::url($this->cover_image),
            'type' => $this->offer->getLabel(),
            'price' => $price,
            'days' => $this->days(),
            'nights' => $this->nights,
            'period' => $this->periodText(),
            'months' => $this->availableMonths(),
            'departures' => $this->departures ?? [],
            'board' => $this->board,
            'stay' => $this->accommodation,
            'badge' => $this->badge,
            'description' => $this->short_description,
            'longDescription' => $this->description,
            'highlights' => $this->highlights ?? [],
            'included' => $this->included ?? [],
            'excluded' => $this->excluded ?? [],
            'itinerary' => $this->itinerary ?? [],

            // Aggiunte del backend
            'id' => $this->id,
            'typeKey' => $this->offer->value,
            'category' => $this->type->value,
            'flightIncluded' => $this->offer->includesFlight(),
            'flightOptional' => $this->offer === PackageOffer::HotelOnly && $this->flight_hotel_enabled,
            'transferIncluded' => $this->isStay() && $this->transfer_included,
            'priceUnit' => $this->price_unit->value,
            'pricePerNight' => $this->isStay() ? $this->lowestBasePrice() : null,
            'currency' => 'EUR',
            ...$this->agencyFee($request, $price),
            'bookingModes' => array_map(fn (BookingMode $mode) => $mode->value, $this->bookingModes()),
            'minNights' => $this->isStay() ? max(1, $this->min_nights) : null,
            'featured' => $this->is_featured,
            'destinationSlug' => $this->destination->slug,
            'validFrom' => $this->valid_from->toDateString(),
            'validTo' => $this->valid_to->toDateString(),
        ];
    }
}
