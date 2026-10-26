<?php

namespace Tests\Feature\Api;

use App\Models\Flight;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fissa: il catalogo demo ha periodi da ottobre dell'anno corrente.
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function hurghada(): Package
    {
        return Package::where('slug', 'hurghada-all-inclusive')->firstOrFail();
    }

    protected function roomType(string $name, string $packageSlug = 'hurghada-all-inclusive'): RoomType
    {
        return Package::where('slug', $packageSlug)->firstOrFail()->hotel->roomTypes()->where('name', $name)->firstOrFail();
    }

    protected function flight(string $airport = 'NAP', string $date = '2026-10-18'): Flight
    {
        return $this->hurghada()->destination->flights()->where('departure_airport', $airport)->whereDate('outbound_date', $date)->firstOrFail();
    }

    protected function insurance(string $name = 'Medico-bagaglio'): Insurance
    {
        return Insurance::where('name', $name)->firstOrFail();
    }

    protected function agencyToken(): string
    {
        return User::where('email', 'agenzia@tourflow.test')->firstOrFail()->createToken('test')->plainTextToken;
    }

    /**
     * 2 adulti, un bambino di 8 anni e un infant.
     *
     * @return array<int, array<string, string>>
     */
    protected function family(bool $complete = true): array
    {
        $passengers = [
            ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M', 'tax_code' => 'BNCLCU85C10H501X'],
            ['first_name' => 'Anna', 'last_name' => 'Verdi', 'birth_date' => '1987-07-22', 'gender' => 'F'],
            ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'birth_date' => '2018-05-01', 'gender' => 'M'],
            ['first_name' => 'Sofia', 'last_name' => 'Bianchi', 'birth_date' => '2026-01-15', 'gender' => 'F'],
        ];

        return $complete ? $passengers : array_map(fn (array $p) => ['birth_date' => $p['birth_date']], $passengers);
    }

    /**
     * Solo soggiorno a Hurghada, 7 notti in autunno, una camera Standard per la famiglia.
     *
     * @return array<string, mixed>
     */
    protected function hotelOnlyCart(array $overrides = []): array
    {
        return [
            'passengers' => $this->family(),
            'items' => [[
                'package_id' => $this->hurghada()->id,
                'mode' => 'hotel_only',
                'check_in' => '2026-11-14',
                'check_out' => '2026-11-21',
                'rooms' => [['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [0, 1, 2, 3]]],
            ]],
            'insurance_ids' => [$this->insurance()->id],
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function booking(string $action, array $cart = [], array $extra = []): array
    {
        return [
            ...($cart ?: $this->hotelOnlyCart()),
            'action' => $action,
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '+39 333 2222222'],
            'terms_accepted' => $action !== 'quote',
            ...$extra,
        ];
    }
}
