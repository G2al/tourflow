<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Excursion extends Model
{
    protected $fillable = [
        'destination_id',
        'name',
        'description',
        'duration',
        'image',
        'adult_price_gross',
        'adult_price_net',
        'child_price_gross',
        'child_price_net',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'adult_price_gross' => 'decimal:2',
            'adult_price_net' => 'decimal:2',
            'child_price_gross' => 'decimal:2',
            'child_price_net' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class);
    }

    public function adultPrice(bool $net): float
    {
        return (float) ($net ? $this->adult_price_net : $this->adult_price_gross);
    }

    public function childPrice(bool $net): float
    {
        $price = $net ? $this->child_price_net : $this->child_price_gross;

        return $price === null ? $this->adultPrice($net) : (float) $price;
    }
}
