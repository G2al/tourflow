<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingAction;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('role', UserRole::Admin)->firstOrFail();
    }

    protected function agencyUser(): User
    {
        return User::where('role', UserRole::Agency)->firstOrFail();
    }

    /**
     * Pratica di un'agenzia: volo + hotel a Hurghada per una coppia, con assicurazione.
     */
    protected function createBooking(BookingAction $action = BookingAction::Confirm): Booking
    {
        $package = Package::where('slug', 'hurghada-all-inclusive')->firstOrFail();

        return app(BookingService::class)->create([
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '3332222222'],
            'passengers' => [
                ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M', 'tax_code' => 'BNCLCU85C10H501X'],
                ['first_name' => 'Anna', 'last_name' => 'Verdi', 'birth_date' => '1987-07-22', 'gender' => 'F'],
            ],
            'items' => [[
                'package_id' => $package->id,
                'mode' => 'flight_hotel',
                'flight_id' => $package->flights()->firstOrFail()->id,
                'rooms' => [['room_type_id' => $package->hotel->roomTypes()->firstOrFail()->id, 'passengers' => [0, 1]]],
            ]],
            'insurance_ids' => [Insurance::firstOrFail()->id],
            'price_lock' => true,
        ], $action, $this->agencyUser(), '127.0.0.1');
    }
}
