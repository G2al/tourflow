<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePeriod extends Model
{
    protected $fillable = [
        'package_id',
        'name',
        'start_date',
        'end_date',
        'adult_price_gross',
        'adult_price_net',
        'extra_bed_adult_price_gross',
        'extra_bed_adult_price_net',
        'child_price_gross',
        'child_price_net',
        'infant_price_gross',
        'infant_price_net',
        'single_supplement_gross',
        'single_supplement_net',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'adult_price_gross' => 'decimal:2',
            'adult_price_net' => 'decimal:2',
            'extra_bed_adult_price_gross' => 'decimal:2',
            'extra_bed_adult_price_net' => 'decimal:2',
            'child_price_gross' => 'decimal:2',
            'child_price_net' => 'decimal:2',
            'infant_price_gross' => 'decimal:2',
            'infant_price_net' => 'decimal:2',
            'single_supplement_gross' => 'decimal:2',
            'single_supplement_net' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Restituisce il prezzo lordo o netto di una voce. Se la voce non è
     * valorizzata si usa il fallback indicato.
     */
    public function price(string $field, bool $net, ?float $fallback = null): ?float
    {
        $value = $this->{$field.($net ? '_net' : '_gross')};

        return $value === null ? $fallback : (float) $value;
    }
}
