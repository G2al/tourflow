<?php

namespace App\Http\Resources;

use App\Models\PackagePeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Periodo tariffario: a persona a notte per i soggiorni, a persona per le
 * escursioni, a servizio per i transfer (solo adultPrice).
 *
 * @mixin PackagePeriod
 */
class PackagePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $float = fn ($value) => $value === null ? null : (float) $value;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'adultPrice' => (float) $this->adult_price,
            'extraBedAdultPrice' => $float($this->extra_bed_adult_price),
            'childPrice' => $float($this->child_price),
            'infantPrice' => $float($this->infant_price),
            'singleSupplement' => $float($this->single_supplement),
        ];
    }
}
