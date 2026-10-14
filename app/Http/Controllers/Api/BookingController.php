<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Package;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    /**
     * Prenotazioni dell'agenzia autenticata.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:pending,confirmed,cancelled']]);

        $bookings = $request->user()->bookings()
            ->with(['package.destination', 'package.hotel', 'participants', 'extras', 'agency'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings);
    }

    /**
     * Richiesta di prenotazione (ospite o agenzia). Nessun pagamento online:
     * la risposta contiene il link WhatsApp per proseguire con il tour operator.
     */
    public function store(StoreBookingRequest $request, BookingService $service): JsonResponse
    {
        $package = Package::findOrFail($request->validated('package_id'));

        $booking = $service->create($package, $request->validated(), $request->user('sanctum'));

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        return new BookingResource($booking->load(['package.destination', 'package.hotel', 'participants', 'extras', 'agency']));
    }
}
