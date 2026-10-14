<?php

namespace App\Http\Resources;

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
        return [
            // Contratto TravelPackage
            'slug' => $this->slug,
            'title' => $this->title,
            'destination' => $this->destination->name,
            'country' => $this->destination->country,
            'location' => $this->location ?? $this->destination->name,
            'image' => Media::url($this->cover_image),
            'type' => $this->type->getLabel(),
            ...$this->price($request, 'price', $this->startingPrice(false), $this->startingPrice(true)),
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
            'typeKey' => $this->type->value,
            'priceUnit' => $this->price_unit->value,
            'priceType' => $this->priceType($request),
            'currency' => 'EUR',
            'featured' => $this->is_featured,
            'destinationSlug' => $this->destination->slug,
            'validFrom' => $this->valid_from->toDateString(),
            'validTo' => $this->valid_to->toDateString(),
        ];
    }
}
