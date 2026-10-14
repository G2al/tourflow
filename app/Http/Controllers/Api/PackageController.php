<?php

namespace App\Http\Controllers\Api;

use App\Enums\PackageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteRequest;
use App\Http\Resources\PackageCardResource;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
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

        $priceColumn = $request->user('sanctum')?->isAgency() ? 'adult_price_net' : 'adult_price_gross';
        $type = $request->filled('type') ? PackageType::fromInput($request->input('type')) : null;

        if ($request->filled('type') && $type === null) {
            throw ValidationException::withMessages(['type' => 'Tipo non valido: usare Soggiorno, Transfer o Escursione.']);
        }

        $packages = Package::published()
            ->with([
                'destination',
                'periods' => fn ($query) => $query->whereDate('end_date', '>=', today()),
            ])
            ->withMin(['periods as min_price' => fn ($query) => $query->whereDate('end_date', '>=', today())], $priceColumn)
            ->when($request->filled('destination'), fn ($query) => $query->whereHas(
                'destination', fn ($q) => $q->where('slug', $request->input('destination'))->orWhere('name', $request->input('destination'))
            ))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($request->filled('days'), fn ($query) => $query->where('nights', $request->integer('days') - 1))
            ->when($request->filled('min_days'), fn ($query) => $query->where('nights', '>=', $request->integer('min_days') - 1))
            ->when($request->filled('max_days'), fn ($query) => $query->where('nights', '<=', $request->integer('max_days') - 1))
            ->when($request->filled('departure'), fn ($query) => $query->whereJsonContains('departures', $request->input('departure')))
            ->when($request->filled('board'), fn ($query) => $query->where('board', $request->input('board')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q
                    ->where('title', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('short_description', 'like', $term)
                    ->orWhereHas('destination', fn ($d) => $d->where('name', 'like', $term)->orWhere('country', 'like', $term))
                    ->orWhereHas('hotel', fn ($h) => $h->where('name', 'like', $term)));
            })
            ->when($request->filled('date'), fn ($query) => $query->whereHas('periods', fn ($q) => $q
                ->whereDate('start_date', '<=', $request->input('date'))
                ->whereDate('end_date', '>=', $request->input('date'))))
            // Filtri sul prezzo "a partire da" (minimo tra i periodi non scaduti).
            ->when($request->filled('max_price'), fn ($query) => $query->whereHas('periods', fn ($q) => $q
                ->whereDate('end_date', '>=', today())
                ->where($priceColumn, '<=', $request->input('max_price'))))
            ->when($request->filled('min_price'), fn ($query) => $query->whereDoesntHave('periods', fn ($q) => $q
                ->whereDate('end_date', '>=', today())
                ->where($priceColumn, '<', $request->input('min_price'))))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->tap(fn ($query) => match ($request->input('sort')) {
                'price_asc' => $query->orderBy('min_price'),
                'price_desc' => $query->orderByDesc('min_price'),
                'duration' => $query->orderBy('nights')->orderBy('min_price'),
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
    public function filters(Request $request): JsonResponse
    {
        $net = (bool) $request->user('sanctum')?->isAgency();
        $packages = Package::published()->with(['destination', 'periods'])->get();

        $sorted = fn ($values) => $values->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $prices = $packages->map(fn (Package $package) => $package->startingPrice($net))->filter();

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

        return new PackageResource($package->load(['destination', 'hotel', 'periods', 'excursions', 'insurances']));
    }

    /**
     * Preventivo senza salvare nulla: utile al frontend per mostrare il
     * totale mentre l'utente compila il form di prenotazione.
     */
    public function quote(QuoteRequest $request, Package $package, PriceCalculator $calculator): JsonResponse
    {
        abort_unless($package->is_active, 404);

        $data = $request->validated();

        return response()->json(['data' => $calculator->calculate(
            $package,
            $data['departure_date'],
            array_column($data['participants'], 'birth_date'),
            $data['excursion_ids'] ?? [],
            $data['insurance_ids'] ?? [],
            (bool) $request->user('sanctum')?->isAgency(),
        )]);
    }
}
