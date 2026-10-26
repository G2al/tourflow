<?php

namespace App\Http\Resources;

use App\Models\LegalPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LegalPage */
class LegalPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'requiresAcceptance' => $this->requires_acceptance,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
