<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingAction;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdvanceBookingRequest;
use App\Http\Requests\Api\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingDocuments;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

/**
 * Pratiche. Vi accede l'agenzia che le ha create (token Bearer) oppure chi
 * possiede il link personale (access token in "?token=" o header "X-Booking-Token").
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    /**
     * Pratiche dell'agenzia autenticata.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $this->bookings->expireOptions();

        $bookings = $request->user()->bookings()
            ->with($this->bookings->relations())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings);
    }

    /**
     * Crea la pratica dal carrello: action = quote (preventivo), option
     * (opzione a tempo) o confirm (conferma).
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create(
            Arr::except($request->validated(), ['action', 'terms_accepted']),
            BookingAction::from($request->validated('action')),
            $request->user('sanctum'),
            $request->ip(),
        );

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        return new BookingResource($booking->load($this->bookings->relations()));
    }

    public function option(AdvanceBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        return new BookingResource($this->bookings->advance($booking, BookingAction::Option, $request->validated(), $request->ip()));
    }

    public function confirm(AdvanceBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        return new BookingResource($this->bookings->advance($booking, BookingAction::Confirm, $request->validated(), $request->ip()));
    }

    public function cancel(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        return new BookingResource($this->bookings->cancel($booking));
    }

    public function quotePdf(Request $request, Booking $booking, BookingDocuments $documents): Response
    {
        $this->authorizeAccess($request, $booking);

        return $documents->quote($booking);
    }

    /**
     * Proforma per l'agenzia (link firmato). La prima apertura rende la
     * prenotazione definitiva.
     */
    public function proforma(Booking $booking, BookingDocuments $documents): Response
    {
        abort_unless($booking->isAgencyBooking() && $booking->proforma_sent_at && ! $booking->status->isFinal(), 404);

        $this->bookings->markProformaViewed($booking);

        return $documents->proforma($booking->refresh());
    }

    private function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user('sanctum');
        $token = (string) ($request->query('token') ?? $request->header('X-Booking-Token') ?? '');

        $allowed = ($user && $booking->user_id === $user->id)
            || ($token !== '' && hash_equals($booking->access_token, $token));

        abort_unless($allowed, 404);

        if ($booking->isOptionExpired()) {
            $this->bookings->expire($booking);
        }
    }
}
