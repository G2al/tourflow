<?php

namespace App\Http\Resources;

use App\Models\Hotel;
use App\Models\RoomType;
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
            'roomTypes' => $this->roomTypes
                ->where('is_active', true)
                ->values()
                ->map(fn (RoomType $roomType) => [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'description' => $roomType->description,
                    'maxOccupancy' => min($roomType->max_occupancy, 4),
                    'supplementPerNight' => (float) $roomType->supplement,
                ]),
        ];
    }
}
