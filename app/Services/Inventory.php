<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\BookingItemRoom;
use App\Models\Flight;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Disponibilità dell'inventario acquistato: camere (allotment per notte) e
 * posti volo. Occupano disponibilità solo le pratiche in opzione (non
 * scaduta) o confermate.
 */
class Inventory
{
    /**
     * Camere libere di una tipologia per TUTTE le notti del soggiorno
     * (il minimo tra le notti).
     */
    public function roomsAvailable(RoomType $roomType, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $excludeBookingId = null): int
    {
        $roomType->loadMissing('allotments');
        $booked = $this->bookedRooms($roomType->id, $checkIn, $checkOut, $excludeBookingId);

        $available = PHP_INT_MAX;
        foreach ($this->nights($checkIn, $checkOut) as $night) {
            $available = min($available, $roomType->allotmentOn($night) - $this->usedOn($booked, $night));
        }

        return $available === PHP_INT_MAX ? 0 : max(0, $available);
    }

    public function seatsAvailable(Flight $flight, ?int $excludeBookingId = null): int
    {
        $used = (int) BookingItem::query()
            ->where('flight_id', $flight->id)
            ->whereHas('booking', fn (Builder $q) => $q->holdingInventory()
                ->when($excludeBookingId, fn (Builder $q) => $q->whereKeyNot($excludeBookingId)))
            ->sum('seats');

        return max(0, $flight->seats - $used);
    }

    /**
     * Verifica una richiesta completa (anche più camere della stessa
     * tipologia e più prodotti sullo stesso volo) e restituisce i problemi
     * indicizzati per chiave (es. "items.0.rooms.1", "items.2.flight").
     *
     * @param  array<int, array{key: string, room_type_id: int, check_in: CarbonImmutable, check_out: CarbonImmutable}>  $rooms
     * @param  array<int, array{key: string, flight_id: int, seats: int}>  $seats
     * @return array<string, string>
     */
    public function problems(array $rooms, array $seats, ?int $excludeBookingId = null): array
    {
        $problems = [];

        foreach (collect($rooms)->groupBy('room_type_id') as $roomTypeId => $requests) {
            $roomType = RoomType::with('allotments')->find($roomTypeId);
            $booked = $this->bookedRooms(
                (int) $roomTypeId,
                $requests->min('check_in'),
                $requests->max('check_out'),
                $excludeBookingId,
            );

            /** @var array<string, int> $demand camere già richieste da questa pratica, per notte */
            $demand = [];
            foreach ($requests as $request) {
                foreach ($this->nights($request['check_in'], $request['check_out']) as $night) {
                    $day = $night->toDateString();
                    $demand[$day] = ($demand[$day] ?? 0) + 1;

                    if ($demand[$day] > $roomType->allotmentOn($night) - $this->usedOn($booked, $night)) {
                        $problems[$request['key']] = "Camera \"{$roomType->name}\" non disponibile per la notte del {$night->format('d/m/Y')}.";

                        break;
                    }
                }
            }
        }

        foreach (collect($seats)->groupBy('flight_id') as $flightId => $requests) {
            $available = $this->seatsAvailable(Flight::findOrFail($flightId), $excludeBookingId);
            $requested = 0;

            foreach ($requests as $request) {
                $requested += $request['seats'];

                if ($requested > $available) {
                    $problems[$request['key']] = "Posti volo insufficienti: disponibili {$available}.";
                }
            }
        }

        return $problems;
    }

    /**
     * @return Collection<int, BookingItemRoom>
     */
    private function bookedRooms(int $roomTypeId, CarbonInterface $from, CarbonInterface $to, ?int $excludeBookingId): Collection
    {
        return BookingItemRoom::query()
            ->where('room_type_id', $roomTypeId)
            ->whereDate('check_in', '<', $to)
            ->whereDate('check_out', '>', $from)
            ->whereHas('item.booking', fn (Builder $q) => $q->holdingInventory()
                ->when($excludeBookingId, fn (Builder $q) => $q->whereKeyNot($excludeBookingId)))
            ->get(['id', 'check_in', 'check_out']);
    }

    /**
     * @param  Collection<int, BookingItemRoom>  $booked
     */
    private function usedOn(Collection $booked, CarbonInterface $night): int
    {
        return $booked->filter(fn (BookingItemRoom $room) => $room->check_in->lte($night) && $room->check_out->gt($night))->count();
    }

    /**
     * Notti di un soggiorno: dal check-in incluso al check-out escluso.
     */
    private function nights(CarbonInterface $checkIn, CarbonInterface $checkOut): CarbonPeriod
    {
        return CarbonPeriod::create($checkIn, '1 day', $checkOut)->excludeEndDate();
    }
}
