<?php

namespace App\Models;

use App\Enums\InsuranceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Assicurazione facoltativa, a persona, aggiungibile a qualunque pratica.
 */
class Insurance extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => InsuranceType::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
