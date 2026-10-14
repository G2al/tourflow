<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Hotel */
class HotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'stars' => $this->stars,
            'address' => $this->address,
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'images' => Media::urls($this->images),
        ];
    }
}
