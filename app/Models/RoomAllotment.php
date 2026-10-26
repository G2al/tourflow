<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Camere di una tipologia acquistate per OGNI NOTTE del periodo (date incluse).
 */
class RoomAllotment extends Model
{
    protected $fillable = [
        'room_type_id',
        'start_date',
        'end_date',
        'rooms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
