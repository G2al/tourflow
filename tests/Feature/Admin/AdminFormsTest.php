<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PriceUnit;
use App\Enums\UserRole;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Destination;
use App\Models\Insurance;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AdminFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('role', UserRole::Admin)->firstOrFail());
    }

    public function test_admin_can_create_a_package_with_periods_and_extras(): void
    {
        $destination = Destination::where('name', 'Marsa Alam')->firstOrFail();
        $excursion = $destination->excursions()->firstOrFail();
        $insurance = Insurance::firstOrFail();

        Livewire::test(CreatePackage::class)
            ->fillForm([
                'type' => 'stay',
                'price_unit' => 'per_person',
                'destination_id' => $destination->id,
                'hotel_id' => $destination->hotels()->value('id'),
                'title' => 'Marsa Alam Capodanno',
                'location' => 'Port Ghalib · Marsa Alam',
                'badge' => 'Ultimi posti',
                'nights' => 7,
                'board' => 'All Inclusive',
                'accommodation' => 'Resort 4 stelle',
                'departures' => ['Aeroporto di Marsa Alam'],
                'valid_from' => '2026-12-20',
                'valid_to' => '2027-01-10',
                'description' => "Primo paragrafo.\n\nSecondo paragrafo.",
                // Stato interno dei repeater "semplici": un elemento per voce.
                'highlights' => [['value' => 'Capodanno sul mare'], ['value' => 'Cenone incluso']],
                'included' => [['value' => '7 pernottamenti']],
                'excluded' => [['value' => 'Volo']],
                'itinerary' => [
                    ['day' => 'Giorno 1', 'title' => 'Arrivo', 'description' => 'Sistemazione in hotel.'],
                ],
                'periods' => [
                    ['name' => 'Capodanno', 'start_date' => '2026-12-20', 'end_date' => '2027-01-10', 'adult_price_gross' => 1200, 'adult_price_net' => 1000],
                ],
                'excursions' => [$excursion->id],
                'insurances' => [$insurance->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('title', 'Marsa Alam Capodanno')->firstOrFail();
        $this->assertSame('marsa-alam-capodanno', $package->slug);
        $this->assertSame(['Capodanno sul mare', 'Cenone incluso'], $package->highlights);
        $this->assertSame(['Aeroporto di Marsa Alam'], $package->departures);
        $this->assertSame('Arrivo', $package->itinerary[0]['title']);
        $this->assertCount(1, $package->periods);
        $this->assertEquals(1000, $package->periods->first()->adult_price_net);
        $this->assertEquals([$excursion->id], $package->excursions->pluck('id')->all());
        $this->assertEquals(0, $package->registration_fee_gross);
    }

    public function test_admin_can_create_a_transfer_priced_per_service(): void
    {
        Livewire::test(CreatePackage::class)
            ->fillForm([
                'destination_id' => Destination::where('name', 'Sharm el Sheikh')->value('id'),
                'title' => 'Transfer privato Sharm',
            ])
            ->fillForm(['type' => 'transfer'])
            ->assertSchemaStateSet(['price_unit' => PriceUnit::PerService, 'nights' => 0])
            ->fillForm([
                'valid_from' => '2026-10-01',
                'valid_to' => '2027-09-30',
                'periods' => [
                    ['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'adult_price_gross' => 25, 'adult_price_net' => 20],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('title', 'Transfer privato Sharm')->firstOrFail();
        $this->assertTrue($package->isPerService());
        $this->assertSame(0, $package->nights);
        $this->assertNull($package->hotel_id);
    }

    public function test_net_price_cannot_exceed_gross_price(): void
    {
        $package = Package::firstOrFail();

        Livewire::test(EditPackage::class, ['record' => $package->id])
            ->fillForm(['registration_fee_gross' => 50, 'registration_fee_net' => 80])
            ->call('save')
            ->assertHasFormErrors(['registration_fee_net' => 'lte']);
    }

    public function test_admin_can_create_an_agency_user(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Paolo Gialli',
                'email' => 'paolo@gialliviaggi.test',
                'role' => 'agency',
                'password' => 'password123',
                'is_active' => true,
                'agency' => [
                    'company_name' => 'Gialli Viaggi',
                    'vat_number' => '55566677788',
                    'address' => 'Via Dante 1',
                    'city' => 'Firenze',
                    'zip_code' => '50100',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'paolo@gialliviaggi.test')->firstOrFail();
        $this->assertTrue($user->isAgency());
        $this->assertSame('Gialli Viaggi', $user->agency->company_name);
    }

    public function test_admin_can_confirm_a_booking(): void
    {
        $package = Package::firstOrFail();
        $booking = app(BookingService::class)->create($package, [
            'departure_date' => '2026-11-14',
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '3332222222'],
            'participants' => [['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M']],
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->id])
            ->callAction('confirm');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_admin_can_update_settings(): void
    {
        Livewire::test(Settings::class)
            ->fillForm(['whatsapp_number' => '393409998877'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('393409998877', Setting::get('whatsapp_number'));
    }
}
