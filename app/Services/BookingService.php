<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(private PriceCalculator $calculator) {}

    /**
     * Crea una richiesta di prenotazione. Il prezzo viene sempre ricalcolato
     * lato server: netto per le agenzie, lordo per gli ospiti.
     *
     * @param  array<string, mixed>  $data  dati validati da StoreBookingRequest
     */
    public function create(Package $package, array $data, ?User $user = null): Booking
    {
        $agency = $user?->isAgency() ? $user->agency : null;
        $net = $agency !== null;

        $participants = array_values($data['participants']);
        $birthDates = array_column($participants, 'birth_date');
        $excursionIds = $data['excursion_ids'] ?? [];
        $insuranceIds = $data['insurance_ids'] ?? [];

        $quote = $this->calculator->calculate($package, $data['departure_date'], $birthDates, $excursionIds, $insuranceIds, $net);
        $grossQuote = $net
            ? $this->calculator->calculate($package, $data['departure_date'], $birthDates, $excursionIds, $insuranceIds)
            : $quote;

        return DB::transaction(function () use ($package, $data, $user, $agency, $participants, $quote, $grossQuote) {
            $booking = Booking::create([
                'package_id' => $package->id,
                'package_period_id' => $quote['period']['id'],
                'user_id' => $user?->id,
                'agency_id' => $agency?->id,
                'status' => BookingStatus::Pending,
                'departure_date' => $quote['departure_date'],
                'return_date' => $quote['return_date'],
                'contact_first_name' => $data['contact']['first_name'],
                'contact_last_name' => $data['contact']['last_name'],
                'contact_email' => $data['contact']['email'],
                'contact_phone' => $data['contact']['phone'],
                'agency_reference' => $agency ? ($data['agency_reference'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'price_type' => $quote['price_type'],
                'total_amount' => $quote['totals']['total'],
                'total_gross' => $grossQuote['totals']['total'],
                'price_breakdown' => $quote,
            ]);

            foreach ($participants as $index => $participant) {
                $booking->participants()->create([
                    ...$participant,
                    'is_lead' => $index === 0,
                    'age_category' => $quote['participants'][$index]['age_category'],
                    'price' => $quote['participants'][$index]['total'],
                ]);
            }

            foreach ($quote['extras'] as $extra) {
                $booking->extras()->create([
                    'type' => $extra['type'],
                    'extraable_type' => $extra['type'],
                    'extraable_id' => $extra['id'],
                    'name' => $extra['name'],
                    'quantity' => $extra['quantity'],
                    'total_price' => $extra['total'],
                ]);
            }

            return $booking->load(['package.destination', 'package.hotel', 'participants', 'extras', 'agency']);
        });
    }
}
