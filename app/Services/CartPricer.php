<?php

namespace App\Services;

use App\Enums\AgeCategory;
use App\Enums\BookingMode;
use App\Models\Agency;
use App\Models\Flight;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\PackagePeriod;
use App\Models\RoomType;
use App\Models\Setting;
use App\Support\AgePolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Calcola il prezzo di un carrello (pratica non ancora salvata).
 *
 * Regole:
 * - soggiorno: prezzi a persona A NOTTE dal periodo che copre ogni notte;
 *   per camera i primi 2 adulti pagano la quota base, 3° e 4° adulto la quota
 *   3°/4° letto, bambini e infant la loro quota; un adulto da solo (senza
 *   bambini) paga il supplemento singola; la tipologia di camera aggiunge il
 *   suo supplemento a persona a notte (infant esclusi);
 * - volo + hotel: le notti sono quelle tra andata e ritorno del volo, più il
 *   prezzo del volo a persona; infant senza posto;
 * - trasferimenti non inclusi e quota d'iscrizione: a persona (infant esclusi);
 * - escursione: a persona; transfer: un prezzo a servizio;
 * - assicurazioni: a persona (infant esclusi); blocca prezzo: a pratica;
 * - fee agenzia: % sul totale della pratica; acconto: % sull'importo dovuto.
 */
class CartPricer
{
    public const MAX_ROOMS = 4;

    public const MAX_ROOM_OCCUPANCY = 4;

    public function __construct(private Inventory $inventory) {}

    /**
     * @param  array<string, mixed>  $cart  dati validati (passengers, items, insurance_ids, price_lock)
     * @return array<string, mixed>
     */
    public function price(array $cart, ?Agency $agency = null, ?int $excludeBookingId = null): array
    {
        $passengers = $this->passengers($cart['passengers']);
        $items = [];
        $roomRequests = [];
        $seatRequests = [];
        $used = [];

        foreach (array_values($cart['items']) as $index => $itemData) {
            $item = $this->priceItem($index, $itemData, $passengers);
            $items[] = $item['result'];
            $roomRequests = [...$roomRequests, ...$item['rooms']];
            $seatRequests = [...$seatRequests, ...$item['seats']];
            $used = [...$used, ...$item['result']['passengers']];
        }

        $unused = array_diff(array_keys($passengers), $used);
        if ($unused !== []) {
            throw ValidationException::withMessages([
                'passengers' => 'Ogni passeggero deve essere assegnato ad almeno un prodotto (posizioni: '.implode(', ', $unused).').',
            ]);
        }

        // Riepilogo passeggeri: categoria alla data del primo servizio.
        $firstDate = CarbonImmutable::parse(collect($items)->min('start_date'));
        $summary = [];
        foreach ($passengers as $position => $passenger) {
            $age = AgePolicy::ageAt($passenger['birth_date'], $firstDate);
            $summary[] = [
                'position' => $position,
                'birth_date' => $passenger['birth_date']->toDateString(),
                'age' => $age,
                'age_category' => AgePolicy::categoryFor($age)->value,
            ];
        }

        $paying = collect($summary)->where('age_category', '!=', AgeCategory::Infant->value)->count();
        $insurances = $this->insurances($cart['insurance_ids'] ?? [], $paying);

        $priceLock = (bool) ($cart['price_lock'] ?? false);
        $priceLockAmount = $priceLock ? Setting::float('price_lock_amount') : 0.0;

        $itemsTotal = round(array_sum(array_column($items, 'total')), 2);
        $insurancesTotal = round(array_sum(array_column($insurances, 'total')), 2);
        $total = round($itemsTotal + $insurancesTotal + $priceLockAmount, 2);

        $feePercent = $agency?->feePercent();
        $feeAmount = $feePercent !== null ? round($total * $feePercent / 100, 2) : 0.0;
        $amountDue = round($total - $feeAmount, 2);
        $depositPercent = Setting::float('deposit_percent');

        $problems = $this->inventory->problems($roomRequests, $seatRequests, $excludeBookingId);
        foreach ($items as &$item) {
            $item['available'] = collect($problems)->keys()->every(fn (string $key) => ! str_starts_with($key, "items.{$item['index']}."));
        }
        unset($item);

        return [
            'currency' => 'EUR',
            'passengers' => $summary,
            'items' => $items,
            'insurances' => $insurances,
            'price_lock' => ['selected' => $priceLock, 'amount' => $priceLockAmount],
            'totals' => [
                'items' => $itemsTotal,
                'insurances' => $insurancesTotal,
                'price_lock' => $priceLockAmount,
                'total' => $total,
                'fee_percent' => $feePercent,
                'fee_amount' => $feeAmount,
                'amount_due' => $amountDue,
                'deposit_percent' => $depositPercent,
                'deposit_amount' => round($amountDue * $depositPercent / 100, 2),
            ],
            'available' => $problems === [],
            'availability_problems' => $problems,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array{birth_date: CarbonImmutable}>
     */
    private function passengers(array $data): array
    {
        $passengers = [];
        foreach (array_values($data) as $position => $passenger) {
            $passengers[$position] = ['birth_date' => CarbonImmutable::parse($passenger['birth_date'])->startOfDay()];
        }

        return $passengers;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{birth_date: CarbonImmutable}>  $passengers
     * @return array{result: array<string, mixed>, rooms: array<int, mixed>, seats: array<int, mixed>}
     */
    private function priceItem(int $index, array $data, array $passengers): array
    {
        $key = "items.{$index}";
        $package = Package::with(['periods', 'hotel.roomTypes', 'destination'])->find($data['package_id']);

        if (! $package || ! $package->is_active || $package->valid_to->lt(today())) {
            throw ValidationException::withMessages(["{$key}.package_id" => 'Il pacchetto selezionato non è disponibile.']);
        }

        $modes = $package->bookingModes();
        $mode = isset($data['mode']) ? BookingMode::tryFrom($data['mode']) : ($modes[0] ?? null);

        if ($mode === null || ! in_array($mode, $modes, true)) {
            throw ValidationException::withMessages(["{$key}.mode" => 'Modalità di prenotazione non disponibile per questo pacchetto.']);
        }

        return $mode->isStay()
            ? $this->priceStay($index, $package, $mode, $data, $passengers)
            : $this->priceService($index, $package, $mode, $data, $passengers);
    }

    /**
     * Solo soggiorno o volo + hotel.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{birth_date: CarbonImmutable}>  $passengers
     * @return array{result: array<string, mixed>, rooms: array<int, mixed>, seats: array<int, mixed>}
     */
    private function priceStay(int $index, Package $package, BookingMode $mode, array $data, array $passengers): array
    {
        $key = "items.{$index}";
        $flight = null;

        if (! $package->hotel) {
            throw ValidationException::withMessages(["{$key}.package_id" => 'Il pacchetto non ha un hotel associato.']);
        }

        if ($mode === BookingMode::FlightHotel) {
            $flight = Flight::query()->bookable()->whereKey($data['flight_id'] ?? 0)->where('destination_id', $package->destination_id)->first()
                ?? throw ValidationException::withMessages(["{$key}.flight_id" => 'Volo non disponibile per questo pacchetto.']);
            $checkIn = CarbonImmutable::parse($flight->outbound_date);
            $checkOut = CarbonImmutable::parse($flight->return_date);
        } else {
            if (empty($data['check_in']) || empty($data['check_out'])) {
                throw ValidationException::withMessages(["{$key}.check_in" => 'Indicare le date di check-in e check-out.']);
            }
            $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
            $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

            if ($checkIn->lte(today())) {
                throw ValidationException::withMessages(["{$key}.check_in" => 'Il check-in deve essere successivo a oggi.']);
            }
        }

        $nights = (int) $checkIn->diffInDays($checkOut);
        if ($nights < max(1, $package->min_nights)) {
            throw ValidationException::withMessages(["{$key}.check_out" => 'Soggiorno minimo: '.max(1, $package->min_nights).' notti.']);
        }

        $periods = [];
        foreach (CarbonPeriod::create($checkIn, '1 day', $checkOut)->excludeEndDate() as $night) {
            $periods[] = $package->periodFor($night) ?? throw ValidationException::withMessages([
                "{$key}.check_in" => "Nessuna tariffa disponibile per la notte del {$night->format('d/m/Y')}.",
            ]);
        }

        $rooms = array_values($data['rooms'] ?? []);
        if ($rooms === [] || count($rooms) > self::MAX_ROOMS) {
            throw ValidationException::withMessages(["{$key}.rooms" => 'Indicare da 1 a '.self::MAX_ROOMS.' camere.']);
        }

        $roomResults = [];
        $roomRequests = [];
        $itemPassengers = [];

        foreach ($rooms as $roomIndex => $room) {
            $roomKey = "{$key}.rooms.{$roomIndex}";
            /** @var RoomType|null $roomType */
            $roomType = $package->hotel->roomTypes->where('is_active', true)->firstWhere('id', $room['room_type_id'] ?? 0)
                ?? throw ValidationException::withMessages(["{$roomKey}.room_type_id" => 'Tipologia di camera non disponibile.']);

            $occupants = $this->occupants($roomKey, $room['passengers'] ?? [], $passengers, $checkIn, $itemPassengers);

            if (count($occupants) > min($roomType->max_occupancy, self::MAX_ROOM_OCCUPANCY)) {
                throw ValidationException::withMessages(["{$roomKey}.passengers" => "La camera \"{$roomType->name}\" ospita al massimo ".min($roomType->max_occupancy, self::MAX_ROOM_OCCUPANCY).' persone.']);
            }

            $roomResults[] = $this->priceRoom($roomIndex, $roomType, $occupants, $periods);
            $roomRequests[] = ['key' => $roomKey, 'room_type_id' => $roomType->id, 'check_in' => $checkIn, 'check_out' => $checkOut];
            $itemPassengers = [...$itemPassengers, ...array_keys($occupants)];
        }

        $allOccupants = [];
        foreach ($roomResults as $roomResult) {
            $allOccupants += $roomResult['categories'];
        }

        $lines = [];
        $seats = [];

        if ($flight) {
            foreach ($allOccupants as $position => $category) {
                $lines[] = [
                    'passenger' => $position,
                    'label' => "Volo A/R {$flight->departure_airport->city()} ({$category->getLabel()})",
                    'amount' => $this->flightPrice($flight, $category),
                ];
            }
            $seatCount = count(array_filter($allOccupants, fn (AgeCategory $category) => $category !== AgeCategory::Infant));
            $seats[] = ['key' => "{$key}.flight", 'flight_id' => $flight->id, 'seats' => $seatCount];
        }

        foreach ($allOccupants as $position => $category) {
            if ($category === AgeCategory::Infant) {
                continue;
            }
            if (! $package->transfer_included && (float) $package->transfer_price > 0) {
                $lines[] = ['passenger' => $position, 'label' => 'Trasferimenti', 'amount' => (float) $package->transfer_price];
            }
            if ((float) $package->registration_fee > 0) {
                $lines[] = ['passenger' => $position, 'label' => "Quota d'iscrizione", 'amount' => (float) $package->registration_fee];
            }
        }

        $total = round(array_sum(array_column($roomResults, 'total')) + array_sum(array_column($lines, 'amount')), 2);

        return [
            'result' => [
                'index' => $index,
                'package_id' => $package->id,
                'package_slug' => $package->slug,
                'title' => $package->title,
                'type' => $package->type->value,
                'mode' => $mode->value,
                'start_date' => $checkIn->toDateString(),
                'end_date' => $checkOut->toDateString(),
                'nights' => $nights,
                'hotel' => ['id' => $package->hotel->id, 'name' => $package->hotel->name],
                'flight' => $flight ? [
                    'id' => $flight->id,
                    'departure_airport' => $flight->departure_airport->value,
                    'departure_city' => $flight->departure_airport->city(),
                    'outbound_date' => $flight->outbound_date->toDateString(),
                    'return_date' => $flight->return_date->toDateString(),
                    'seats' => $seats[0]['seats'],
                ] : null,
                'rooms' => array_map(fn (array $room) => array_diff_key($room, ['categories' => true]), $roomResults),
                'passengers' => $itemPassengers,
                'lines' => $lines,
                'total' => $total,
            ],
            'rooms' => $roomRequests,
            'seats' => $seats,
        ];
    }

    /**
     * Prezzo di una camera notte per notte.
     *
     * @param  array<int, AgeCategory>  $occupants  posizione => categoria
     * @param  array<int, PackagePeriod>  $periods  periodo di ogni notte
     * @return array<string, mixed>
     */
    private function priceRoom(int $roomIndex, RoomType $roomType, array $occupants, array $periods): array
    {
        $adults = count(array_filter($occupants, fn (AgeCategory $c) => $c === AgeCategory::Adult));
        $children = count(array_filter($occupants, fn (AgeCategory $c) => $c === AgeCategory::Child));
        $nights = count($periods);

        $perPassenger = array_fill_keys(array_keys($occupants), 0.0);
        $single = 0.0;

        foreach ($periods as $period) {
            $adultIndex = 0;
            foreach ($occupants as $position => $category) {
                $base = $period->price('adult_price');
                $perPassenger[$position] += match ($category) {
                    AgeCategory::Adult => ++$adultIndex <= 2 ? $base : $period->price('extra_bed_adult_price', $base),
                    AgeCategory::Child => $period->price('child_price', $base),
                    AgeCategory::Infant => $period->price('infant_price', 0.0),
                };
            }

            if ($adults === 1 && $children === 0) {
                $single += $period->price('single_supplement', 0.0);
            }
        }

        $lines = [];
        foreach ($occupants as $position => $category) {
            $lines[] = ['passenger' => $position, 'label' => "Quota {$category->getLabel()} ({$nights} notti)", 'amount' => round($perPassenger[$position], 2)];
        }

        $payingGuests = $adults + $children;
        if ((float) $roomType->supplement > 0 && $payingGuests > 0) {
            $lines[] = [
                'passenger' => null,
                'label' => "Supplemento {$roomType->name} ({$payingGuests} pers. × {$nights} notti)",
                'amount' => round((float) $roomType->supplement * $payingGuests * $nights, 2),
            ];
        }

        if ($single > 0) {
            $lines[] = ['passenger' => null, 'label' => "Supplemento singola ({$nights} notti)", 'amount' => round($single, 2)];
        }

        return [
            'index' => $roomIndex,
            'room_type_id' => $roomType->id,
            'room_type' => $roomType->name,
            'passengers' => array_keys($occupants),
            'lines' => $lines,
            'total' => round(array_sum(array_column($lines, 'amount')), 2),
            'categories' => $occupants,
        ];
    }

    /**
     * Escursione (a persona) o transfer (a servizio) in una data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{birth_date: CarbonImmutable}>  $passengers
     * @return array{result: array<string, mixed>, rooms: array<int, mixed>, seats: array<int, mixed>}
     */
    private function priceService(int $index, Package $package, BookingMode $mode, array $data, array $passengers): array
    {
        $key = "items.{$index}";

        if (empty($data['date'])) {
            throw ValidationException::withMessages(["{$key}.date" => 'Indicare la data del servizio.']);
        }

        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        if ($date->lte(today())) {
            throw ValidationException::withMessages(["{$key}.date" => 'La data deve essere successiva a oggi.']);
        }

        $period = $package->periodFor($date) ?? throw ValidationException::withMessages([
            "{$key}.date" => 'Nessuna tariffa disponibile per la data selezionata.',
        ]);

        $used = [];
        $occupants = $this->occupants($key, $data['passengers'] ?? [], $passengers, $date, $used);

        $lines = [];
        if ($mode === BookingMode::Transfer) {
            $lines[] = ['passenger' => null, 'label' => 'Prezzo del servizio', 'amount' => $period->price('adult_price')];
        } else {
            foreach ($occupants as $position => $category) {
                $base = $period->price('adult_price');
                $lines[] = ['passenger' => $position, 'label' => "Quota {$category->getLabel()}", 'amount' => match ($category) {
                    AgeCategory::Adult => $base,
                    AgeCategory::Child => $period->price('child_price', $base),
                    AgeCategory::Infant => $period->price('infant_price', 0.0),
                }];
            }
        }

        return [
            'result' => [
                'index' => $index,
                'package_id' => $package->id,
                'package_slug' => $package->slug,
                'title' => $package->title,
                'type' => $package->type->value,
                'mode' => $mode->value,
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
                'nights' => 0,
                'hotel' => null,
                'flight' => null,
                'rooms' => [],
                'passengers' => array_keys($occupants),
                'lines' => $lines,
                'total' => round(array_sum(array_column($lines, 'amount')), 2),
            ],
            'rooms' => [],
            'seats' => [],
        ];
    }

    /**
     * Passeggeri assegnati a una camera o a un servizio, con la categoria
     * d'età alla data indicata. Serve almeno un adulto; un passeggero non può
     * stare due volte nello stesso prodotto.
     *
     * @param  array<int, mixed>  $positions
     * @param  array<int, array{birth_date: CarbonImmutable}>  $passengers
     * @param  array<int, int>  $alreadyUsed
     * @return array<int, AgeCategory>
     */
    private function occupants(string $key, array $positions, array $passengers, CarbonImmutable $date, array $alreadyUsed): array
    {
        if ($positions === []) {
            throw ValidationException::withMessages(["{$key}.passengers" => 'Assegnare almeno un passeggero.']);
        }

        $occupants = [];
        foreach ($positions as $position) {
            $position = (int) $position;

            if (! isset($passengers[$position])) {
                throw ValidationException::withMessages(["{$key}.passengers" => "Passeggero {$position} inesistente."]);
            }
            if (isset($occupants[$position]) || in_array($position, $alreadyUsed, true)) {
                throw ValidationException::withMessages(["{$key}.passengers" => "Il passeggero {$position} è assegnato due volte."]);
            }

            $occupants[$position] = AgePolicy::categoryFor(AgePolicy::ageAt($passengers[$position]['birth_date'], $date));
        }

        if (! in_array(AgeCategory::Adult, $occupants, true)) {
            throw ValidationException::withMessages(["{$key}.passengers" => 'È necessario almeno un adulto.']);
        }

        // Adulti prima: la quota 3°/4° letto va al terzo e quarto adulto.
        uasort($occupants, fn (AgeCategory $a, AgeCategory $b) => ($a === AgeCategory::Adult ? 0 : 1) <=> ($b === AgeCategory::Adult ? 0 : 1));

        return $occupants;
    }

    private function flightPrice(Flight $flight, AgeCategory $category): float
    {
        $adult = (float) $flight->adult_price;

        return match ($category) {
            AgeCategory::Adult => $adult,
            AgeCategory::Child => $flight->child_price === null ? $adult : (float) $flight->child_price,
            AgeCategory::Infant => (float) ($flight->infant_price ?? 0),
        };
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function insurances(array $ids, int $persons): array
    {
        $available = Insurance::active()->whereIn('id', $ids)->get()->keyBy('id');

        return collect(array_unique($ids))->map(function ($id) use ($available, $persons) {
            $insurance = $available->get((int) $id) ?? throw ValidationException::withMessages([
                'insurance_ids' => "L'assicurazione selezionata (#{$id}) non è disponibile.",
            ]);

            return [
                'id' => $insurance->id,
                'name' => $insurance->name,
                'type' => $insurance->type->value,
                'unit_price' => (float) $insurance->price,
                'quantity' => $persons,
                'total' => round($persons * (float) $insurance->price, 2),
            ];
        })->values()->all();
    }
}
