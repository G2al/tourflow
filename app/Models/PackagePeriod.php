<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo tariffario di un pacchetto. Il significato dei prezzi dipende dal tipo:
 * - soggiorno: a persona A NOTTE (adulto in doppia, 3°/4° letto, bambino, infant, singola);
 * - escursione: a persona;
 * - transfer: a servizio (solo adult_price).
 */
class PackagePeriod extends Model
{
    protected $fillable = [
        'package_id',
        'name',
        'start_date',
        'end_date',
        'adult_price',
        'extra_bed_adult_price',
        'child_price',
        'infant_price',
        'single_supplement',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'adult_price' => 'decimal:2',
            'extra_bed_adult_price' => 'decimal:2',
            'child_price' => 'decimal:2',
            'infant_price' => 'decimal:2',
            'single_supplement' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Prezzo di una voce, o il fallback se la voce non è valorizzata.
     */
    public function price(string $field, ?float $fallback = null): ?float
    {
        $value = $this->{$field};

        return $value === null ? $fallback : (float) $value;
    }
}
