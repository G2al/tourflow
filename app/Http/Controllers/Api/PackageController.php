<?php

namespace App\Http\Controllers\Api;

use App\Enums\Airport;
use App\Enums\PackageOffer;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageCardResource;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    /**
     * Prezzo "a partire da" in SQL (stessa regola di Package::startingPrice):
     * quota base minima tra i periodi futuri × notti consigliate nei soggiorni,
     * più il volo abbinato più economico quando il volo è compreso.
     * Parametri: [oggi, oggi].
     */
    private const STARTING_PRICE_SQL = "((select min(pp.adult_price) from package_periods pp where pp.package_id = packages.id and pp.end_date >= ?)
        * (case when packages.type = 'stay' and packages.nights > 0 then packages.nights else 1 end)
        + (case when packages.offer in ('flight_hotel', 'full_package') then
            (select min(f.adult_price) from flights f join flight_package fp on fp.flight_id = f.id
             where fp.package_id = packages.id and f.is_active = 1 and f.outbound_date > ?)
          else 0 end))";

    /**
     * Elenco pacchetti pubblicati. Filtri: destination (slug o nome), type
     * (offerta o "Soggiorno"), search, days / min_days / max_days, departure
     * (punto d'incontro), airport (volo da NAP/FCO/MXP/BRI), board, date,
     * min_price / max_price, featured.
     * Ordinamento: sort = recommended|price_asc|price_desc|duration|newest.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'destination' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'days' => ['nullable', 'integer', 'min:1'],
            'min_days' => ['nullable', 'integer', 'min:1'],
            'max_days' => ['nullable', 'integer', 'min:1'],
            'departure' => ['nullable', 'string', 'max:255'],
            'board' => ['nullable', 'string', 'max:255'],
            'airport' => ['nullable', Rule::enum(Airport::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:recommended,price_asc,price_desc,duration,newest,rating'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $offers = $request->filled('type') ? PackageOffer::fromFilter($request->input('type')) : [];

        if ($request->filled('type') && $offers === []) {
            throw ValidationException::withMessages(['type' => 'Tipo non valido: usare Solo soggiorno, Volo + hotel, Pacchetto completo, Transfer o Escursione.']);
        }

        $today = today()->toDateString();

        $packages = Package::published()
            ->select('packages.*')
            ->selectRaw(self::STARTING_PRICE_SQL.' as starting_price', [$today, $today])
            ->with([
                'destination',
                'periods' => fn ($query) => $query->whereDate('end_date', '>=', today()),
                'bookableFlights',
            ])
            ->when($request->filled('destination'), fn (Builder $query) => $query->whereHas(
                'destination', fn ($q) => $q->where('slug', $request->input('destination'))->orWhere('name', $request->input('destination'))
            ))
            ->when($offers, fn (Builder $query) => $query->whereIn('offer', $offers))
            ->when($request->filled('airport'), fn (Builder $query) => $query
                ->where('flight_hotel_enabled', true)
                ->whereHas('bookableFlights', fn ($q) => $q->where('departure_airport', $request->input('airport'))))
            ->when($request->filled('days'), fn (Builder $query) => $query->where('nights', $request->integer('days') - 1))
            ->when($request->filled('min_days'), fn (Builder $query) => $query->where('nights', '>=', $request->integer('min_days') - 1))
            ->when($request->filled('max_days'), fn (Builder $query) => $query->where('nights', '<=', $request->integer('max_days') - 1))
            ->when($request->filled('departure'), fn (Builder $query) => $query->whereJsonContains('departures', $request->input('departure')))
            ->when($request->filled('board'), fn (Builder $query) => $query->where('board', $request->input('board')))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q
                    ->where('title', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('short_description', 'like', $term)
                    ->orWhereHas('destination', fn ($d) => $d->where('name', 'like', $term)->orWhere('country', 'like', $term))
                    ->orWhereHas('hotel', fn ($h) => $h->where('name', 'like', $term)));
            })
            ->when($request->filled('date'), fn (Builder $query) => $query->whereHas('periods', fn ($q) => $q
                ->whereDate('start_date', '<=', $request->input('date'))
                ->whereDate('end_date', '>=', $request->input('date'))))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->whereRaw(self::STARTING_PRICE_SQL.' <= CAST(? AS DECIMAL(10,2))', [$today, $today, $request->input('max_price')]))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->whereRaw(self::STARTING_PRICE_SQL.' >= CAST(? AS DECIMAL(10,2))', [$today, $today, $request->input('min_price')]))
            ->when($request->boolean('featured'), fn (Builder $query) => $query->where('is_featured', true))
            ->tap(fn (Builder $query) => match ($request->input('sort')) {
                'price_asc' => $query->orderBy('starting_price'),
                'price_desc' => $query->orderByDesc('starting_price'),
                'duration' => $query->orderBy('nights')->orderBy('starting_price'),
                'newest' => $query->latest(),
                // "recommended" e "rating" (recensioni non gestite): ordine scelto dall'admin.
                default => $query->orderByDesc('is_featured')->orderBy('sort_order')->latest(),
            })
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        return PackageCardResource::collection($packages);
    }

    /**
     * Valori disponibili per i filtri del catalogo, ricavati dai pacchetti pubblicati.
     */
    public function filters(): JsonResponse
    {
        $packages = Package::published()->with(['destination', 'periods', 'bookableFlights'])->get();

        $sorted = fn ($values) => $values->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $prices = $packages->map(fn (Package $package) => $package->startingPrice())->filter();
        $airports = $packages->where('flight_hotel_enabled', true)->pluck('bookableFlights')->flatten()->pluck('departure_airport')->unique();

        return response()->json(['data' => [
            'destinations' => $packages->pluck('destination')->unique('id')->sortBy('sort_order')->values()
                ->map(fn ($destination) => ['name' => $destination->name, 'slug' => $destination->slug, 'country' => $destination->country]),
            'types' => collect(PackageOffer::cases())
                ->filter(fn (PackageOffer $offer) => $packages->contains('offer', $offer))
                ->map(fn (PackageOffer $offer) => ['key' => $offer->value, 'label' => $offer->getLabel()])->values(),
            'airports' => collect(Airport::cases())
                ->filter(fn (Airport $airport) => $airports->contains($airport))
                ->map(fn (Airport $airport) => ['code' => $airport->value, 'city' => $airport->city()])->values(),
            'departures' => $sorted($packages->pluck('departures')->flatten()),
            'boards' => $sorted($packages->pluck('board')),
            'days' => $packages->map(fn (Package $package) => $package->days())->unique()->sort()->values(),
            'priceRange' => ['min' => $prices->min(), 'max' => $prices->max()],
        ]]);
    }

    public function show(Package $package): PackageResource
    {
        abort_unless($package->is_active, 404);

        return new PackageResource($package->load(['destination', 'hotel.roomTypes', 'periods', 'bookableFlights']));
    }
}
