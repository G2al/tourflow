<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Pratica: uno o più prodotti (soggiorni, volo + hotel, escursioni, transfer)
 * con passeggeri, assicurazioni e totali calcolati dal server.
 */
class Booking extends Model
{
    protected $fillable = [
        'code',
        'access_token',
        'user_id',
        'agency_id',
        'status',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_phone',
        'agency_reference',
        'notes',
        'admin_notes',
        'items_total',
        'insurances_total',
        'price_lock',
        'price_lock_amount',
        'total',
        'fee_percent',
        'fee_amount',
        'amount_due',
        'deposit_percent',
        'deposit_amount',
        'price_breakdown',
        'quote_valid_until',
        'option_expires_at',
        'option_reminders_sent',
        'confirmed_at',
        'proforma_sent_at',
        'proforma_viewed_at',
        'cancelled_at',
        'terms_accepted_at',
        'terms_accepted_ip',
        'accepted_legal_pages',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'items_total' => 'decimal:2',
            'insurances_total' => 'decimal:2',
            'price_lock' => 'boolean',
            'price_lock_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'price_breakdown' => 'array',
            'option_reminders_sent' => 'array',
            'quote_valid_until' => 'date',
            'option_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'proforma_sent_at' => 'datetime',
            'proforma_viewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'accepted_legal_pages' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (blank($booking->code)) {
                do {
                    $code = 'QT-'.now()->format('y').'-'.Str::upper(Str::random(6));
                } while (static::where('code', $code)->exists());

                $booking->code = $code;
            }

            $booking->access_token ??= Str::random(48);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(BookingPassenger::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('id');
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(BookingInsurance::class);
    }

    /**
     * Pratiche che occupano disponibilità in questo momento (le opzioni solo
     * se non scadute).
     */
    public function scopeHoldingInventory(Builder $query): Builder
    {
        return $query
            ->whereIn('status', BookingStatus::holdingInventory())
            ->where(fn (Builder $q) => $q
                ->where('status', '!=', BookingStatus::Option)
                ->orWhere('option_expires_at', '>', now()));
    }

    public function isAgencyBooking(): bool
    {
        return $this->agency_id !== null;
    }

    public function isOptionExpired(): bool
    {
        return $this->status === BookingStatus::Option && $this->option_expires_at?->isPast();
    }

    /**
     * Pagina della pratica sul sito pubblico (link personale).
     */
    public function publicUrl(): string
    {
        return config('tourflow.frontend_url')."/pratica/{$this->code}?token={$this->access_token}";
    }

    public function contactFullName(): string
    {
        return "{$this->contact_first_name} {$this->contact_last_name}";
    }

    public function leadPassenger(): ?BookingPassenger
    {
        return $this->passengers->firstWhere('is_lead', true) ?? $this->passengers->first();
    }
}
