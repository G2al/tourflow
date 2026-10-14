<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesPrices;
use App\Models\PackagePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PackagePeriod */
class PackagePeriodResource extends JsonResource
{
    use ExposesPrices;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            ...$this->price($request, 'adultPrice', $this->adult_price_gross, $this->adult_price_net),
            ...$this->price($request, 'extraBedAdultPrice', $this->extra_bed_adult_price_gross, $this->extra_bed_adult_price_net),
            ...$this->price($request, 'childPrice', $this->child_price_gross, $this->child_price_net),
            ...$this->price($request, 'infantPrice', $this->infant_price_gross, $this->infant_price_net),
            ...$this->price($request, 'singleSupplement', $this->single_supplement_gross, $this->single_supplement_net),
        ];
    }
}
