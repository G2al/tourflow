<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasUniqueSlug;

    protected $fillable = [
        'destination_id',
        'name',
        'slug',
        'stars',
        'address',
        'description',
        'amenities',
        'images',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'images' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class)->orderBy('sort_order')->orderBy('id');
    }
}
