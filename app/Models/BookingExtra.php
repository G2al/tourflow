<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingExtra extends Model
{
    protected $fillable = [
        'booking_id',
        'type',
        'extraable_type',
        'extraable_id',
        'name',
        'quantity',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function extraable(): MorphTo
    {
        return $this->morphTo();
    }
}
