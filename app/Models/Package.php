<?php

namespace App\Models;

use App\Enums\AgeCategory;
use App\Enums\PackageType;
use App\Enums\PriceUnit;
use App\Models\Concerns\HasUniqueSlug;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Package extends Model
{
    use HasUniqueSlug, SoftDeletes;

    public const MONTHS = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile', 5 => 'Maggio', 6 => 'Giugno',
        7 => 'Luglio', 8 => 'Agosto', 9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
    ];

    protected string $slugSource = 'title';

    protected $fillable = [
        'destination_id',
        'hotel_id',
        'type',
        'price_unit',
        'title',
        'location',
        'badge',
        'slug',
        'short_description',
        'description',
        'highlights',
        'itinerary',
        'included',
        'excluded',
        'cover_image',
        'gallery',
        'nights',
        'board',
        'accommodation',
        'valid_from',
        'valid_to',
        'period_label',
        'departures',
        'transfer_included',
        'transfer_price_gross',
        'transfer_price_net',
        'registration_fee_gross',
        'registration_fee_net',
        'infant_max_age',
        'child_max_age',
        'cancellation_policy',
        'notes',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => PackageType::class,
            'price_unit' => PriceUnit::class,
            'gallery' => 'array',
            'highlights' => 'array',
            'itinerary' => 'array',
            'included' => 'array',
            'excluded' => 'array',
            'departures' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'transfer_included' => 'boolean',
            'transfer_price_gross' => 'decimal:2',
            'transfer_price_net' => 'decimal:2',
            'registration_fee_gross' => 'decimal:2',
            'registration_fee_net' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(PackagePeriod::class)->orderBy('start_date');
    }

    public function excursions(): BelongsToMany
    {
        return $this->belongsToMany(Excursion::class);
    }

    public function insurances(): BelongsToMany
    {
        return $this->belongsToMany(Insurance::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Pacchetti attivi e non ancora scaduti.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereDate('valid_to', '>=', today());
    }

    public function isPerService(): bool
    {
        return $this->price_unit === PriceUnit::PerService;
    }

    public function days(): int
    {
        return $this->nights + 1;
    }

    public function periodFor(CarbonInterface $date): ?PackagePeriod
    {
        return $this->periods
            ->first(fn (PackagePeriod $period) => $date->between($period->start_date, $period->end_date));
    }

    public function ageCategoryFor(int $age): AgeCategory
    {
        return match (true) {
            $age <= $this->infant_max_age => AgeCategory::Infant,
            $age <= $this->child_max_age => AgeCategory::Child,
            default => AgeCategory::Adult,
        };
    }

    /**
     * @return Collection<int, PackagePeriod>
     */
    public function upcomingPeriods(): Collection
    {
        return $this->periods
            ->filter(fn (PackagePeriod $period) => $period->end_date->gte(today()))
            ->values();
    }

    /**
     * Prezzo "a partire da" (quota base più bassa tra i periodi futuri).
     */
    public function startingPrice(bool $net): ?float
    {
        $price = $this->upcomingPeriods()->min($net ? 'adult_price_net' : 'adult_price_gross');

        return $price === null ? null : (float) $price;
    }

    /**
     * Mesi (in ordine cronologico a partire da oggi) in cui il pacchetto è
     * prenotabile, ricavati dai periodi tariffari futuri.
     *
     * @return array<int, int>
     */
    public function availableMonthNumbers(): array
    {
        $months = [];

        foreach ($this->upcomingPeriods() as $period) {
            $start = $period->start_date->copy()->max(today())->startOfMonth();

            foreach (CarbonPeriod::create($start, '1 month', $period->end_date) as $month) {
                $months[$month->format('Y-m')] = $month->month;
            }
        }

        ksort($months);

        return array_values(array_unique($months));
    }

    /**
     * Nomi dei mesi disponibili in ordine di calendario (Gennaio → Dicembre).
     *
     * @return array<int, string>
     */
    public function availableMonths(): array
    {
        $numbers = $this->availableMonthNumbers();
        sort($numbers);

        return array_map(fn (int $month) => self::MONTHS[$month], $numbers);
    }

    /**
     * Testo sintetico del periodo: quello inserito dall'admin oppure ricavato
     * dai mesi disponibili ("Tutto l'anno", "Novembre - Aprile").
     */
    public function periodText(): ?string
    {
        if (filled($this->period_label)) {
            return $this->period_label;
        }

        $months = $this->availableMonthNumbers();

        return match (true) {
            $months === [] => null,
            count($months) === 12 => "Tutto l'anno",
            count($months) === 1 => self::MONTHS[$months[0]],
            default => self::MONTHS[$months[0]].' - '.self::MONTHS[end($months)],
        };
    }
}
