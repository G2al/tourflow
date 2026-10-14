<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Destination;
use App\Models\Excursion;
use App\Models\Hotel;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', UserRole::Admin)->firstOrFail();
    }

    public function test_agency_users_cannot_access_the_panel(): void
    {
        $this->actingAs(User::where('role', UserRole::Agency)->firstOrFail())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_every_admin_page_renders(): void
    {
        $package = Package::firstOrFail();
        $booking = app(BookingService::class)->create($package, [
            'departure_date' => '2026-11-14',
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '3332222222'],
            'participants' => [['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M']],
            'insurance_ids' => [$package->insurances()->value('insurances.id')],
        ], User::where('role', UserRole::Agency)->first());

        $urls = [
            '/admin',
            '/admin/bookings',
            "/admin/bookings/{$booking->id}",
            "/admin/bookings/{$booking->id}/edit",
            '/admin/packages',
            '/admin/packages/create',
            "/admin/packages/{$package->id}/edit",
            '/admin/destinations',
            '/admin/destinations/create',
            '/admin/destinations/'.Destination::first()->id.'/edit',
            '/admin/hotels',
            '/admin/hotels/'.Hotel::first()->id.'/edit',
            '/admin/excursions',
            '/admin/excursions/'.Excursion::first()->id.'/edit',
            '/admin/insurances',
            '/admin/insurances/'.Insurance::first()->id.'/edit',
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.User::where('role', UserRole::Agency)->first()->id.'/edit',
            '/admin/settings',
        ];

        $this->actingAs($this->admin());

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_booking_page_shows_participants_and_agency(): void
    {
        $package = Package::firstOrFail();
        $booking = app(BookingService::class)->create($package, [
            'departure_date' => '2026-11-14',
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '3332222222'],
            'participants' => [['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M', 'tax_code' => 'BNCLCU85C10H501X']],
        ], User::where('role', UserRole::Agency)->first());

        $this->actingAs($this->admin())
            ->get("/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('BNCLCU85C10H501X')
            ->assertSee('Viaggi Sole Srl');
    }
}
