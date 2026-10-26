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

            // Soggiorno minimo facoltativo: 1 = nessun minimo. Escursioni e
            // transfer si svolgono in giornata.
            $package->min_nights = max(1, (int) $package->min_nights);
            $package->nights = $offer->isStay() ? $package->min_nights : 0;

            if (! $offer->isStay()) {
                $package->hotel_id = null;
            }

            if (blank($package->title)) {
                $package->title = static::autoTitle(
                    $offer,
                    $package->destination?->name ?? Destination::find($package->destination_id)?->name,
                    $package->hotel_id ? Hotel::find($package->hotel_id)?->name : null,
                );
            }
        });
    }

    /**
     * Titolo proposto in base a cosa si vende, dove e con quale hotel
     * (es. "Soggiorno a Hurghada · Red Sea Paradise Resort").
     */
    public static function autoTitle(PackageOffer $offer, ?string $destination, ?string $hotel = null): string
    {
        $what = match ($offer) {
            PackageOffer::HotelOnly => 'Soggiorno',
            default => $offer->getLabel(),
        };

        return trim($what.($destination ? " a {$destination}" : '').($offer->isStay() && $hotel ? " · {$hotel}" : ''));
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

    /**
     * Notti mostrate nel catalogo: quelle del volo più conveniente per le
     * offerte con volo, il soggiorno minimo per il solo soggiorno, 0 per
     * escursioni e transfer.
     */
    public function displayNights(): int
    {
        if (! $this->isStay()) {
            return 0;
        }

        if ($this->offer?->includesFlight()) {
            return $this->cheapestFlightOption()['flight']?->nights() ?? max(1, $this->min_nights);
        }

        return max(1, $this->min_nights);
    }

    public function days(): int
    {
        return $this->displayNights() + 1;
    }

    /**
     * Su cosa è calcolato il prezzo "a partire da".
     */
    public function priceBasis(): string
    {
        return match ($this->offer) {
            PackageOffer::HotelOnly => 'per_night',
            PackageOffer::Transfer => 'per_service',
            default => 'per_person',
        };
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
     * Prezzo "a partire da" mostrato nel catalogo (vedi priceBasis()):
     * - solo soggiorno: la quota a notte più bassa;
     * - volo + hotel e pacchetto completo: a persona, hotel per le notti del
     *   volo più il volo, sulla combinazione più conveniente;
     * - escursione: a persona; transfer: a servizio.
     */
    public function startingPrice(): ?float
    {
        if ($this->offer?->includesFlight()) {
            return $this->cheapestFlightOption()['total'];
        }

        return $this->lowestBasePrice();
    }

    /**
     * Combinazione volo + hotel più economica tra i voli abbinati, stimata con
     * la quota a notte più bassa.
     *
     * @return array{flight: Flight|null, total: float|null}
     */
    public function cheapestFlightOption(): array
    {
        $nightly = $this->lowestBasePrice();

        if ($nightly === null) {
            return ['flight' => null, 'total' => null];
        }

        $best = $this->bookableFlights
            ->map(fn (Flight $flight) => ['flight' => $flight, 'total' => round($nightly * $flight->nights() + (float) $flight->adult_price, 2)])
            ->sortBy('total')
            ->first();

        return $best ?? ['flight' => null, 'total' => null];
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
     * Testo sintetico del periodo, ricavato dai mesi disponibili
     * ("Tutto l'anno", "Novembre - Aprile").
     */
    public function periodText(): ?string
    {
        $months = $this->availableMonthNumbers();

        return match (true) {
            $months === [] => null,
            count($months) === 12 => "Tutto l'anno",
            count($months) === 1 => self::MONTHS[$months[0]],
            default => self::MONTHS[$months[0]].' - '.self::MONTHS[end($months)],
        };
    }
}
