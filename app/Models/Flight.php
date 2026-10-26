<?php

namespace App\Models;

use App\Enums\Airport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Volo andata/ritorno acquistato dal tour operator, con posti limitati.
 * I prezzi sono a persona per l'intero A/R.
 */
class Flight extends Model
{
    protected $fillable = [
        'destination_id',
        'departure_airport',
        'outbound_date',
        'return_date',
        'carrier',
        'outbound_details',
        'return_details',
        'seats',
        'adult_price',
        'child_price',
        'infant_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'departure_airport' => Airport::class,
            'outbound_date' => 'date',
            'return_date' => 'date',
            'adult_price' => 'decimal:2',
            'child_price' => 'decimal:2',
            'infant_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Voli attivi con partenza da domani in poi.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereDate('outbound_date', '>', today());
    }

    public function nights(): int
    {
        return (int) $this->outbound_date->diffInDays($this->return_date);
    }

    public function label(): string
    {
        return "{$this->departure_airport->getLabel()} · {$this->outbound_date->format('d/m/Y')} - {$this->return_date->format('d/m/Y')}";
    }
}
