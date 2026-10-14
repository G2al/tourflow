<?php

namespace App\Models;

use App\Enums\InsuranceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Insurance extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'price_gross',
        'price_net',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => InsuranceType::class,
            'price_gross' => 'decimal:2',
            'price_net' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class);
    }

    public function price(bool $net): float
    {
        return (float) ($net ? $this->price_net : $this->price_gross);
    }
}
