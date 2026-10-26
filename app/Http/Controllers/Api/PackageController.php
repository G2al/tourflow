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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    /**
     * Elenco pacchetti pubblicati. Filtri: destination (slug o nome), type
     * (offerta o "Soggiorno"), search, days / min_days / max_days, departure
     * (punto d'incontro), airport (volo da NAP/FCO/MXP/BRI), board, date,
     * min_price / max_price, featured.
     * Ordinamento: sort = recommended|price_asc|price_desc|duration|newest.
     *
     * Prezzo e durata dipendono da periodi e voli abbinati (Package::startingPrice
     * e displayNights): filtri e ordinamenti su questi valori sono applicati
     * sugli stessi calcoli mostrati nelle card, poi si pagina.
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

        $packages = Package::published()
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
            ->when($request->boolean('featured'), fn (Builder $query) => $query->where('is_featured', true))
            // "recommended" e "rating" (recensioni non gestite): ordine scelto dall'admin.
            ->orderByDesc('is_featured')->orderBy('sort_order')->latest()
            ->get();

        $price = fn (Package $package) => $package->startingPrice();
        $packages = $packages
            ->when($request->filled('days'), fn ($c) => $c->filter(fn (Package $p) => $p->days() === $request->integer('days')))
            ->when($request->filled('min_days'), fn ($c) => $c->filter(fn (Package $p) => $p->days() >= $request->integer('min_days')))
            ->when($request->filled('max_days'), fn ($c) => $c->filter(fn (Package $p) => $p->days() <= $request->integer('max_days')))
            ->when($request->filled('min_price'), fn ($c) => $c->filter(fn (Package $p) => $price($p) !== null && $price($p) >= (float) $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($c) => $c->filter(fn (Package $p) => $price($p) !== null && $price($p) <= (float) $request->input('max_price')));

        $packages = match ($request->input('sort')) {
            'price_asc' => $packages->sortBy(fn (Package $p) => $price($p) ?? PHP_FLOAT_MAX),
            'price_desc' => $packages->sortByDesc(fn (Package $p) => $price($p) ?? -1),
            'duration' => $packages->sortBy(fn (Package $p) => [$p->days(), $price($p) ?? PHP_FLOAT_MAX]),
            'newest' => $packages->sortByDesc('created_at'),
            default => $packages,
        };

        $perPage = $request->integer('per_page', 12);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return PackageCardResource::collection(new LengthAwarePaginator(
            $packages->values()->forPage($page, $perPage)->values(),
            $packages->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
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
