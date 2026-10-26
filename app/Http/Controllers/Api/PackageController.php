<?php

namespace App\Http\Controllers\Api;

use App\Enums\PackageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageCardResource;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    /**
     * Prezzo "a partire da" in SQL: quota base minima tra i periodi futuri,
     * moltiplicata per le notti consigliate nei soggiorni.
     */
    private const STARTING_PRICE_SQL = "(select min(pp.adult_price) from package_periods pp where pp.package_id = packages.id and pp.end_date >= ?)
        * (case when packages.type = 'stay' and packages.nights > 0 then packages.nights else 1 end)";

    /**
     * Elenco pacchetti pubblicati. Filtri: destination (slug o nome), type
     * (Soggiorno|Transfer|Escursione), search, days / min_days / max_days,
     * departure (punto d'incontro), board, date (Y-m-d), min_price / max_price,
     * featured. Ordinamento: sort = recommended|price_asc|price_desc|duration|newest.
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
            'date' => ['nullable', 'date_format:Y-m-d'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:recommended,price_asc,price_desc,duration,newest,rating'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = $request->filled('type') ? PackageType::fromInput($request->input('type')) : null;

        if ($request->filled('type') && $type === null) {
            throw ValidationException::withMessages(['type' => 'Tipo non valido: usare Soggiorno, Transfer o Escursione.']);
        }

        $today = today()->toDateString();

        $packages = Package::published()
            ->select('packages.*')
            ->selectRaw(self::STARTING_PRICE_SQL.' as starting_price', [$today])
            ->with([
                'destination',
                'periods' => fn ($query) => $query->whereDate('end_date', '>=', today()),
            ])
            ->when($request->filled('destination'), fn (Builder $query) => $query->whereHas(
                'destination', fn ($q) => $q->where('slug', $request->input('destination'))->orWhere('name', $request->input('destination'))
            ))
            ->when($type, fn (Builder $query) => $query->where('type', $type))
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
            ->when($request->filled('max_price'), fn (Builder $query) => $query->whereRaw(self::STARTING_PRICE_SQL.' <= CAST(? AS DECIMAL(10,2))', [$today, $request->input('max_price')]))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->whereRaw(self::STARTING_PRICE_SQL.' >= CAST(? AS DECIMAL(10,2))', [$today, $request->input('min_price')]))
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
        $packages = Package::published()->with(['destination', 'periods'])->get();

        $sorted = fn ($values) => $values->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $prices = $packages->map(fn (Package $package) => $package->startingPrice())->filter();

        return response()->json(['data' => [
            'destinations' => $packages->pluck('destination')->unique('id')->sortBy('sort_order')->values()
                ->map(fn ($destination) => ['name' => $destination->name, 'slug' => $destination->slug, 'country' => $destination->country]),
            'types' => collect(PackageType::cases())
                ->filter(fn (PackageType $type) => $packages->contains('type', $type))
                ->map(fn (PackageType $type) => $type->getLabel())->values(),
            'departures' => $sorted($packages->pluck('departures')->flatten()),
            'boards' => $sorted($packages->pluck('board')),
            'days' => $packages->map(fn (Package $package) => $package->days())->unique()->sort()->values(),
            'priceRange' => ['min' => $prices->min(), 'max' => $prices->max()],
        ]]);
    }

    public function show(Package $package): PackageResource
    {
        abort_unless($package->is_active, 404);

        return new PackageResource($package->load(['destination', 'hotel.roomTypes', 'periods', 'flights']));
    }
}
