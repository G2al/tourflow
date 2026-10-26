<?php

namespace App\Http\Resources;

use App\Models\Flight;
use App\Services\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Volo A/R prenotabile come "volo + hotel". Prezzi a persona.
 *
 * @mixin Flight
 */
class FlightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departureAirport' => $this->departure_airport->value,
            'departureAirportName' => $this->departure_airport->getLabel(),
            'departureCity' => $this->departure_airport->city(),
            'outboundDate' => $this->outbound_date->toDateString(),
            'returnDate' => $this->return_date->toDateString(),
            'nights' => $this->nights(),
            'carrier' => $this->carrier,
            'outboundDetails' => $this->outbound_details,
            'returnDetails' => $this->return_details,
            'adultPrice' => (float) $this->adult_price,
            'childPrice' => $this->child_price === null ? (float) $this->adult_price : (float) $this->child_price,
            'infantPrice' => (float) ($this->infant_price ?? 0),
            'seatsAvailable' => app(Inventory::class)->seatsAvailable($this->resource),
        ];
    }
}
