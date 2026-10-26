<?php

namespace App\Models;

use App\Enums\BookingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prodotto di una pratica: soggiorno (solo hotel o volo + hotel), escursione
 * o transfer. "passenger_positions" indica i passeggeri della pratica coinvolti.
 */
class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'package_id',
        'mode',
        'hotel_id',
        'flight_id',
        'title',
        'start_date',
        'end_date',
        'nights',
        'seats',
        'passenger_positions',
        'total',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'mode' => BookingMode::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'passenger_positions' => 'array',
            'total' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(BookingItemRoom::class)->orderBy('id');
    }
}
