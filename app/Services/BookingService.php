<?php

namespace App\Services;

use App\Enums\BookingAction;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\LegalPage;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo di vita delle pratiche: creazione dal carrello (preventivo, opzione o
 * conferma), passaggi successivi, annullamento, scadenza delle opzioni e
 * apertura della proforma da parte dell'agenzia.
 */
class BookingService
{
    private const PASSENGER_FIELDS = [
        'first_name', 'last_name', 'birth_date', 'gender', 'tax_code', 'nationality',
        'document_type', 'document_number', 'document_expiry',
    ];

    public function __construct(private CartPricer $pricer) {}

    /**
     * Prezzo del carrello senza salvare nulla.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    public function quote(array $cart, ?User $user = null): array
    {
        return $this->pricer->price($cart, $this->agencyOf($user));
    }

    /**
     * @param  array<string, mixed>  $data  dati validati da StoreBookingRequest
     */
    public function create(array $data, BookingAction $action, ?User $user = null, ?string $ip = null): Booking
    {
        $agency = $this->agencyOf($user);
        $passengers = array_values($data['passengers']);

        if ($action->holdsInventory()) {
            $this->assertPassengersComplete($passengers);
        }

        return DB::transaction(function () use ($data, $action, $user, $agency, $ip, $passengers) {
            $this->lockInventory($data['items']);

            $pricing = $this->pricer->price($data, $agency);
            if ($action->holdsInventory()) {
                $this->assertAvailable($pricing);
            }

            $booking = Booking::create([
                'user_id' => $user?->id,
                'agency_id' => $agency?->id,
                'status' => BookingStatus::Quote,
                'contact_first_name' => $data['contact']['first_name'],
                'contact_last_name' => $data['contact']['last_name'],
                'contact_email' => $data['contact']['email'],
                'contact_phone' => $data['contact']['phone'],
                'agency_reference' => $agency ? ($data['agency_reference'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                ...$this->amounts($pricing, $this->cartRequest($data)),
            ]);

            foreach ($passengers as $position => $passenger) {
                $booking->passengers()->create([
                    ...Arr::only($passenger, self::PASSENGER_FIELDS),
                    'position' => $position,
                    'is_lead' => $position === 0,
                    'age_category' => $pricing['passengers'][$position]['age_category'],
                ]);
            }

            $this->storeLines($booking, $pricing);
            $this->apply($booking, $action, $ip);

            return $booking;
        })->load($this->relations());
    }

    /**
     * Passaggio successivo: preventivo → opzione/conferma, opzione → conferma.
     * Senza "blocca prezzo" il prezzo viene ricalcolato con il listino attuale.
     *
     * @param  array<string, mixed>  $data  passeggeri (facoltativi, per completare i dati)
     */
    public function advance(Booking $booking, BookingAction $action, array $data = [], ?string $ip = null): Booking
    {
        $allowed = match ($booking->status) {
            BookingStatus::Quote => [BookingAction::Option, BookingAction::Confirm],
            BookingStatus::Option => $booking->isOptionExpired() ? [] : [BookingAction::Confirm],
            default => [],
        };

        if (! in_array($action, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Operazione non consentita: la pratica è \"{$booking->status->getLabel()}\"".($booking->isOptionExpired() ? ' (opzione scaduta)' : '').'.',
            ]);
        }

        if (! empty($data['passengers'])) {
            $this->updatePassengers($booking, array_values($data['passengers']));
        }

        $this->assertPassengersComplete($booking->passengers()->get()->map->only(self::PASSENGER_FIELDS)->all());

        return DB::transaction(function () use ($booking, $action, $ip) {
            $request = $booking->price_breakdown['request'];
            $this->lockInventory($request['items']);

            $pricing = $this->pricer->price($request, $booking->agency, $booking->id);
            $this->assertAvailable($pricing);

            if (! $booking->price_lock) {
                $booking->update($this->amounts($pricing, $request));
                $booking->items()->delete();
                $booking->insurances()->delete();
                $this->storeLines($booking, $pricing);
            }

            $this->apply($booking, $action, $ip);

            return $booking;
        })->load($this->relations());
    }

    /**
     * Annullamento da parte del cliente o dell'agenzia: solo preventivi e opzioni.
     */
    public function cancel(Booking $booking): Booking
    {
        if (! in_array($booking->status, [BookingStatus::Quote, BookingStatus::Option], true)) {
            throw ValidationException::withMessages([
                'status' => 'Solo preventivi e opzioni possono essere annullati online. Contatta il tour operator.',
            ]);
        }

        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

        return $booking->load($this->relations());
    }

    /**
     * Opzioni scadute → stato "scaduta" (la disponibilità è già libera).
     */
    public function expireOptions(): int
    {
        return Booking::query()
            ->where('status', BookingStatus::Option)
            ->where('option_expires_at', '<=', now())
            ->update(['status' => BookingStatus::Expired, 'updated_at' => now()]);
    }

    /**
     * L'agenzia ha aperto la proforma: la prenotazione diventa definitiva.
     */
    public function markProformaViewed(Booking $booking): void
    {
        if ($booking->status === BookingStatus::ProformaSent) {
            $booking->update(['status' => BookingStatus::ProformaViewed, 'proforma_viewed_at' => now()]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['passengers', 'items.rooms.roomType', 'items.flight', 'items.hotel', 'items.package', 'insurances', 'agency', 'user'];
    }

    private function agencyOf(?User $user): ?Agency
    {
        return $user?->isAgency() ? $user->agency : null;
    }

    private function apply(Booking $booking, BookingAction $action, ?string $ip): void
    {
        $attributes = match ($action) {
            BookingAction::Quote => [
                'status' => BookingStatus::Quote,
                'quote_valid_until' => today()->addDays(Setting::int('quote_validity_days')),
            ],
            BookingAction::Option => [
                'status' => BookingStatus::Option,
                'option_expires_at' => now()->addHours(Setting::int($booking->isAgencyBooking() ? 'option_hours_agency' : 'option_hours_guest')),
            ],
            BookingAction::Confirm => $booking->isAgencyBooking()
                ? ['status' => BookingStatus::ProformaSent, 'confirmed_at' => now(), 'proforma_sent_at' => now(), 'option_expires_at' => null]
                : ['status' => BookingStatus::Confirmed, 'confirmed_at' => now(), 'option_expires_at' => null],
        };

        if ($action->holdsInventory()) {
            $attributes += [
                'terms_accepted_at' => now(),
                'terms_accepted_ip' => $ip,
                'accepted_legal_pages' => LegalPage::active()->where('requires_acceptance', true)->get()
                    ->map(fn (LegalPage $page) => ['id' => $page->id, 'slug' => $page->slug, 'title' => $page->title, 'version' => $page->updated_at?->toIso8601String()])
                    ->all(),
            ];
        }

        $booking->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function amounts(array $pricing, array $request): array
    {
        $totals = $pricing['totals'];

        return [
            'items_total' => $totals['items'],
            'insurances_total' => $totals['insurances'],
            'price_lock' => $pricing['price_lock']['selected'],
            'price_lock_amount' => $totals['price_lock'],
            'total' => $totals['total'],
            'fee_percent' => $totals['fee_percent'],
            'fee_amount' => $totals['fee_amount'],
            'amount_due' => $totals['amount_due'],
            'deposit_percent' => $totals['deposit_percent'],
            'deposit_amount' => $totals['deposit_amount'],
            'price_breakdown' => [...Arr::except($pricing, ['availability_problems']), 'request' => $request],
        ];
    }

    /**
     * Salva prodotti, camere e assicurazioni calcolati.
     *
     * @param  array<string, mixed>  $pricing
     */
    private function storeLines(Booking $booking, array $pricing): void
    {
        foreach ($pricing['items'] as $item) {
            $bookingItem = $booking->items()->create([
                'package_id' => $item['package_id'],
                'mode' => $item['mode'],
                'hotel_id' => $item['hotel']['id'] ?? null,
                'flight_id' => $item['flight']['id'] ?? null,
                'title' => $item['title'],
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'],
                'nights' => $item['nights'],
                'seats' => $item['flight']['seats'] ?? 0,
                'passenger_positions' => $item['passengers'],
                'total' => $item['total'],
                'breakdown' => $item,
            ]);

            foreach ($item['rooms'] as $room) {
                $bookingItem->rooms()->create([
                    'room_type_id' => $room['room_type_id'],
                    'check_in' => $item['start_date'],
                    'check_out' => $item['end_date'],
                    'passenger_positions' => $room['passengers'],
                    'total' => $room['total'],
                ]);
            }
        }

        foreach ($pricing['insurances'] as $insurance) {
            $booking->insurances()->create([
                'insurance_id' => $insurance['id'],
                'name' => $insurance['name'],
                'unit_price' => $insurance['unit_price'],
                'quantity' => $insurance['quantity'],
                'total' => $insurance['total'],
            ]);
        }
    }

    /**
     * Struttura del carrello conservata nella pratica per ricalcolare il
     * prezzo nei passaggi successivi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cartRequest(array $data): array
    {
        return [
            'passengers' => array_map(fn (array $p) => ['birth_date' => $p['birth_date']], array_values($data['passengers'])),
            'items' => array_values($data['items']),
            'insurance_ids' => array_values($data['insurance_ids'] ?? []),
            'price_lock' => (bool) ($data['price_lock'] ?? false),
        ];
    }

    /**
     * Blocca le righe di inventario coinvolte per evitare doppie vendite
     * in caso di richieste simultanee.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function lockInventory(array $items): void
    {
        $roomTypeIds = collect($items)->pluck('rooms')->flatten(1)->pluck('room_type_id')->filter()->unique()->all();
        $flightIds = collect($items)->pluck('flight_id')->filter()->unique()->all();

        if ($roomTypeIds) {
            RoomType::whereIn('id', $roomTypeIds)->lockForUpdate()->get();
        }
        if ($flightIds) {
            Flight::whereIn('id', $flightIds)->lockForUpdate()->get();
        }
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function assertAvailable(array $pricing): void
    {
        if (! $pricing['available']) {
            throw ValidationException::withMessages($pricing['availability_problems']);
        }
    }

    /**
     * Per bloccare disponibilità servono nome, cognome e sesso di ogni passeggero.
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    private function assertPassengersComplete(array $passengers): void
    {
        $errors = [];
        foreach (array_values($passengers) as $position => $passenger) {
            foreach (['first_name' => 'nome', 'last_name' => 'cognome', 'gender' => 'sesso'] as $field => $label) {
                if (blank($passenger[$field] ?? null)) {
                    $errors["passengers.{$position}.{$field}"] = "Il campo {$label} è obbligatorio per bloccare la pratica.";
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Completa i dati anagrafici: stesso numero di passeggeri e stesse date
     * di nascita (che determinano il prezzo).
     *
     * @param  array<int, array<string, mixed>>  $passengers
     */
    private function updatePassengers(Booking $booking, array $passengers): void
    {
        $existing = $booking->passengers()->get();

        if (count($passengers) !== $existing->count()) {
            throw ValidationException::withMessages(['passengers' => 'Il numero di passeggeri non può cambiare.']);
        }

        foreach ($existing as $passenger) {
            $data = $passengers[$passenger->position];

            if ($data['birth_date'] !== $passenger->birth_date->toDateString()) {
                throw ValidationException::withMessages([
                    "passengers.{$passenger->position}.birth_date" => 'La data di nascita non può cambiare: crea un nuovo preventivo.',
                ]);
            }

            $passenger->update(Arr::only($data, self::PASSENGER_FIELDS));
        }
    }
}
