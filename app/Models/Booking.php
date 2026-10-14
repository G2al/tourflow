<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'code',
        'package_id',
        'package_period_id',
        'user_id',
        'agency_id',
        'status',
        'departure_date',
        'return_date',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_phone',
        'agency_reference',
        'notes',
        'admin_notes',
        'price_type',
        'total_amount',
        'total_gross',
        'price_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'departure_date' => 'date',
            'return_date' => 'date',
            'total_amount' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'price_breakdown' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (blank($booking->code)) {
                do {
                    $code = 'TF-'.now()->format('y').'-'.Str::upper(Str::random(6));
                } while (static::where('code', $code)->exists());

                $booking->code = $code;
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PackagePeriod::class, 'package_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class)->orderByDesc('is_lead')->orderBy('id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(BookingExtra::class);
    }

    public function isNetPrice(): bool
    {
        return $this->price_type === 'net';
    }

    public function contactFullName(): string
    {
        return "{$this->contact_first_name} {$this->contact_last_name}";
    }
}
