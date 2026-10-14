<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesPrices;
use App\Models\Insurance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Insurance */
class InsuranceResource extends JsonResource
{
    use ExposesPrices;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'description' => $this->description,
            ...$this->price($request, 'price', $this->price_gross, $this->price_net),
        ];
    }
}
