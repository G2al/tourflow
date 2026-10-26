<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\PackageOffer;
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

    protected static function booted(): void
    {
        // L'offerta ("cosa si vende") determina tipo, modalità e regole.
        static::saving(function (Package $package) {
            $offer = $package->offer ?? PackageOffer::HotelOnly;

            $package->type = $offer->packageType();
            $package->price_unit = $offer === PackageOffer::Transfer ? PriceUnit::PerService : PriceUnit::PerPerson;

            // Solo soggiorno: il volo facoltativo dipende da flight_hotel_enabled.
            $package->hotel_only_enabled = $offer === PackageOffer::HotelOnly;
            if ($offer !== PackageOffer::HotelOnly) {
                $package->flight_hotel_enabled = $offer->includesFlight();
            }

            if ($offer === PackageOffer::FullPackage) {
                $package->transfer_included = true;
            }

            // Escursioni e transfer si svolgono in giornata.
            if (! $offer->isStay()) {
                $package->nights = 0;
                $package->hotel_id = null;
            }
        });
    }

    protected $fillable = [
        'destination_id',
        'hotel_id',
        'offer',
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
        'hotel_only_enabled',
        'flight_hotel_enabled',
        'min_nights',
        'transfer_included',
        'transfer_price',
        'registration_fee',
        'cancellation_policy',
        'notes',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'offer' => PackageOffer::class,
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
            'hotel_only_enabled' => 'boolean',
            'flight_hotel_enabled' => 'boolean',
            'transfer_included' => 'boolean',
            'transfer_price' => 'decimal:2',
            'registration_fee' => 'decimal:2',
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

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * Voli abbinati al pacchetto (scelti nel pannello).
     */
    public function flights(): BelongsToMany
    {
        return $this->belongsToMany(Flight::class)
            ->orderBy('outbound_date')
            ->orderBy('departure_airport');
    }

    /**
     * Voli abbinati ancora prenotabili (attivi, con partenza da domani).
     */
    public function bookableFlights(): BelongsToMany
    {
        return $this->flights()
            ->where('flights.is_active', true)
            ->whereDate('flights.outbound_date', '>', today());
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

    public function isStay(): bool
    {
        return $this->type === PackageType::Stay;
    }

    /**
     * Modalità con cui il pacchetto si può prenotare.
     *
     * @return array<int, BookingMode>
     */
    public function bookingModes(): array
    {
        return match ($this->type) {
            PackageType::Stay => array_values(array_filter([
                $this->hotel_only_enabled ? BookingMode::HotelOnly : null,
                $this->flight_hotel_enabled ? BookingMode::FlightHotel : null,
            ])),
            PackageType::Excursion => [BookingMode::Excursion],
            PackageType::Transfer => [BookingMode::Transfer],
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
     * Quota base più bassa tra i periodi futuri: a notte per i soggiorni,
     * a persona per le escursioni, a servizio per i transfer.
     */
    public function lowestBasePrice(): ?float
    {
        $price = $this->upcomingPeriods()->min('adult_price');

        return $price === null ? null : (float) $price;
    }

    /**
     * Prezzo "a partire da" mostrato nel catalogo, a persona: per i soggiorni
     * è la quota a notte più bassa per le notti consigliate, più il volo più
     * economico quando il volo è compreso (volo + hotel, pacchetto completo).
     */
    public function startingPrice(): ?float
    {
        $base = $this->lowestBasePrice();

        if ($base === null) {
            return null;
        }

        if (! $this->isStay()) {
            return $base;
        }

        $price = $base * max(1, $this->nights);

        if ($this->offer?->includesFlight()) {
            $flight = $this->bookableFlights->min('adult_price');

            if ($flight === null) {
                return null;
            }

            $price += (float) $flight;
        }

        return round($price, 2);
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
