<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Support\Media;
use Illuminate\Http\Request;

/**
 * Dettaglio del pacchetto: tutti i campi della card più galleria, hotel,
 * listino, extra e condizioni.
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
            'transfer' => [
                'included' => $this->transfer_included,
                ...$this->price($request, 'price', $this->transfer_included ? null : $this->transfer_price_gross, $this->transfer_included ? null : $this->transfer_price_net),
            ],
            ...$this->price($request, 'registrationFee', $this->registration_fee_gross, $this->registration_fee_net),
            'ageRanges' => [
                'infant' => ['min' => 0, 'max' => $this->infant_max_age],
                'child' => ['min' => $this->infant_max_age + 1, 'max' => $this->child_max_age],
                'adult' => ['min' => $this->child_max_age + 1, 'max' => null],
            ],
            'cancellationPolicy' => $this->cancellation_policy,
            'notes' => $this->notes,
            'periods' => PackagePeriodResource::collection($this->upcomingPeriods()),
            'excursions' => ExcursionResource::collection($this->excursions->where('is_active', true)->values()),
            'insurances' => InsuranceResource::collection($this->insurances->where('is_active', true)->values()),
        ];
    }
}
