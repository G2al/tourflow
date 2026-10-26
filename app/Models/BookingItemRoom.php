<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Camera di un soggiorno: occupa una camera della tipologia per ogni notte
 * da check_in (incluso) a check_out (escluso).
 */
class BookingItemRoom extends Model
{
    protected $fillable = [
        'booking_item_id',
        'room_type_id',
        'check_in',
        'check_out',
        'passenger_positions',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'passenger_positions' => 'array',
            'total' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
