<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipologia di camera di un hotel (es. Standard, Deluxe vista mare), con
 * supplemento a persona a notte rispetto alla quota base.
 */
class RoomType extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'max_occupancy',
        'supplement',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supplement' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(RoomAllotment::class)->orderBy('start_date');
    }

    /**
     * Camere acquistate per una notte (somma degli allotment che la coprono).
     */
    public function allotmentOn(CarbonInterface $night): int
    {
        return (int) $this->allotments
            ->filter(fn (RoomAllotment $allotment) => $night->between($allotment->start_date, $allotment->end_date))
            ->sum('rooms');
    }
}
