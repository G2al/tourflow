<?php

namespace App\Http\Resources;

use App\Models\Destination;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Destination */
class DestinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'country' => $this->country,
            'description' => $this->description,
            'image' => Media::url($this->cover_image),
            'packagesCount' => $this->whenCounted('packages'),
        ];
    }
}
