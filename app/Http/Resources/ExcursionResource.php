<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesPrices;
use App\Models\Excursion;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Excursion */
class ExcursionResource extends JsonResource
{
    use ExposesPrices;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'image' => Media::url($this->image),
            ...$this->price($request, 'adultPrice', $this->adult_price_gross, $this->adult_price_net),
            ...$this->price($request, 'childPrice', $this->child_price_gross ?? $this->adult_price_gross, $this->child_price_net ?? $this->adult_price_net),
        ];
    }
}
