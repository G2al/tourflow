<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Support\AgePolicy;
use App\Support\Media;
use Illuminate\Http\Request;

/**
 * Dettaglio del pacchetto: tutti i campi della card più galleria, hotel con
 * tipologie di camera, voli disponibili, listino e condizioni.
 *
 * @mixin Package
 */
class PackageResource extends PackageCardResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'gallery' => Media::urls($this->gallery),
            'hotel' => $this->hotel ? new HotelResource($this->hotel) : null,
            'flights' => $this->flight_hotel_enabled ? FlightResource::collection($this->flights) : [],
            'transfer' => [
                'included' => $this->transfer_included,
                'price' => $this->transfer_included || $this->transfer_price === null ? null : (float) $this->transfer_price,
            ],
            'registrationFee' => (float) $this->registration_fee,
            'ageRanges' => AgePolicy::ranges(),
            'cancellationPolicy' => $this->cancellation_policy,
            'notes' => $this->notes,
            'periods' => PackagePeriodResource::collection($this->upcomingPeriods()),
        ];
    }
}
