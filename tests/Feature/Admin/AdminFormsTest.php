<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingAction;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\PackageOffer;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Flights\Pages\CreateFlight;
use App\Filament\Resources\Hotels\Pages\EditHotel;
use App\Filament\Resources\Hotels\RelationManagers\RoomTypesRelationManager;
use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Destination;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

class AdminFormsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin());
    }

    public function test_admin_can_create_a_hotel_only_stay_with_optional_flight(): void
    {
        $destination = Destination::where('name', 'Marsa Alam')->firstOrFail();

        Livewire::test(CreatePackage::class)
            ->fillForm([
                'offer' => 'hotel_only',
                'destination_id' => $destination->id,
                'hotel_id' => $destination->hotels()->value('id'),
                'title' => 'Marsa Alam Capodanno',
                'location' => 'Port Ghalib · Marsa Alam',
                'nights' => 7,
                'min_nights' => 4,
                'flight_hotel_enabled' => true,
                'board' => 'All Inclusive',
                'valid_from' => '2026-12-20',
                'valid_to' => '2027-01-10',
                // Stato interno dei repeater "semplici": un elemento per voce.
                'highlights' => [['value' => 'Capodanno sul mare']],
                'periods' => [
                    ['name' => 'Capodanno', 'start_date' => '2026-12-20', 'end_date' => '2027-01-10', 'adult_price' => 150, 'child_price' => 75],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('title', 'Marsa Alam Capodanno')->firstOrFail();
        $this->assertSame('marsa-alam-capodanno', $package->slug);
        $this->assertSame(4, $package->min_nights);
        $this->assertTrue($package->flight_hotel_enabled);
        $this->assertTrue($package->hotel_only_enabled);
        $this->assertSame(['Capodanno sul mare'], $package->highlights);
        $this->assertEquals(150, $package->periods->first()->adult_price);
        // Solo soggiorno: prezzo "a partire da" a notte.
        $this->assertEquals(150, $package->startingPrice());
        $this->assertSame('per_night', $package->priceBasis());
    }

    public function test_title_is_generated_but_can_be_edited(): void
    {
        $destination = Destination::where('name', 'Marsa Alam')->firstOrFail();
        $hotel = $destination->hotels()->firstOrFail();
        $base = [
            'valid_from' => '2026-12-20',
            'valid_to' => '2027-01-10',
            'periods' => [['start_date' => '2026-12-20', 'end_date' => '2027-01-10', 'adult_price' => 100]],
        ];

        Livewire::test(CreatePackage::class)
            ->fillForm(['offer' => 'hotel_only'])
            ->fillForm(['destination_id' => $destination->id])
            ->fillForm(['hotel_id' => $hotel->id])
            ->assertSchemaStateSet(['title' => "Soggiorno a Marsa Alam · {$hotel->name}"])
            ->fillForm($base)
            ->call('create')
            ->assertHasNoFormErrors();

        // Un titolo scritto a mano non viene sovrascritto cambiando l'offerta.
        Livewire::test(CreatePackage::class)
            ->fillForm(['offer' => 'excursion', 'destination_id' => $destination->id])
            ->fillForm(['title' => 'Snorkeling con i delfini'])
            ->fillForm(['offer' => 'transfer'])
            ->assertSchemaStateSet(['title' => 'Snorkeling con i delfini']);
    }

    public function test_registration_fee_is_applied_only_when_enabled(): void
    {
        $package = Package::where('slug', 'marsa-alam-family')->firstOrFail();

        Livewire::test(EditPackage::class, ['record' => $package->id])
            ->assertSchemaStateSet(['has_registration_fee' => false])
            ->fillForm(['has_registration_fee' => true, 'registration_fee' => 40])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEquals(40, $package->fresh()->registration_fee);

        Livewire::test(EditPackage::class, ['record' => $package->id])
            ->assertSchemaStateSet(['has_registration_fee' => true])
            ->fillForm(['has_registration_fee' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEquals(0, $package->fresh()->registration_fee);
    }

    public function test_full_package_requires_flights_and_includes_transfers(): void
    {
        $destination = Destination::where('name', 'Hurghada')->firstOrFail();
        $flights = $destination->flights()->where('departure_airport', 'NAP')->pluck('id')->all();
        $form = [
            'offer' => 'full_package',
            'destination_id' => $destination->id,
            'hotel_id' => $destination->hotels()->value('id'),
            'title' => 'Hurghada tutto incluso',
            'transfer_included' => false,
            'valid_from' => '2026-10-01',
            'valid_to' => '2027-09-30',
            'periods' => [['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'adult_price' => 80]],
        ];

        Livewire::test(CreatePackage::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasFormErrors(['flights' => 'required']);

        Livewire::test(CreatePackage::class)
            ->fillForm([...$form, 'flights' => $flights])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('title', 'Hurghada tutto incluso')->firstOrFail();
        $this->assertSame(PackageOffer::FullPackage, $package->offer);
        $this->assertSame([BookingMode::FlightHotel], $package->bookingModes());
        $this->assertTrue($package->transfer_included);
        $this->assertCount(8, $package->flights);
        // 80 × 7 notti + volo più economico da Napoli (290)
        $this->assertEquals(850, $package->startingPrice());
    }

    public function test_stays_require_a_hotel(): void
    {
        Livewire::test(CreatePackage::class)
            ->fillForm([
                'offer' => 'hotel_only',
                'destination_id' => Destination::where('name', 'Marsa Alam')->value('id'),
                'title' => 'Senza hotel',
                'valid_from' => '2026-12-20',
                'valid_to' => '2027-01-10',
            ])
            ->call('create')
            ->assertHasFormErrors(['hotel_id' => 'required']);
    }

    public function test_admin_can_create_a_transfer_priced_per_service(): void
    {
        Livewire::test(CreatePackage::class)
            ->fillForm([
                'destination_id' => Destination::where('name', 'Sharm el Sheikh')->value('id'),
                'title' => 'Transfer privato Sharm',
            ])
            ->fillForm(['offer' => 'transfer'])
            ->assertSchemaStateSet(['nights' => 0])
            ->fillForm([
                'valid_from' => '2026-10-01',
                'valid_to' => '2027-09-30',
                'periods' => [
                    ['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'adult_price' => 25],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('title', 'Transfer privato Sharm')->firstOrFail();
        $this->assertTrue($package->isPerService());
        $this->assertSame(0, $package->nights);
        $this->assertNull($package->hotel_id);
    }

    public function test_admin_can_add_a_room_type_with_allotment(): void
    {
        $hotel = Hotel::firstOrFail();

        Livewire::test(RoomTypesRelationManager::class, ['ownerRecord' => $hotel, 'pageClass' => EditHotel::class])
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Family room',
                'supplement' => 10,
                'max_occupancy' => 4,
                'allotments' => [
                    ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'rooms' => 3],
                ],
            ])
            ->assertHasNoActionErrors();

        $roomType = RoomType::where('name', 'Family room')->firstOrFail();
        $this->assertSame(3, $roomType->allotmentOn(now()->setDate(2026, 11, 15)));
    }

    public function test_admin_can_create_a_flight(): void
    {
        Livewire::test(CreateFlight::class)
            ->fillForm([
                'destination_id' => Destination::where('name', 'Marsa Alam')->value('id'),
                'departure_airport' => 'BRI',
                'outbound_date' => '2026-11-01',
                'return_date' => '2026-11-08',
                'seats' => 40,
                'adult_price' => 320,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(7, Flight::where('departure_airport', 'BRI')->whereDate('outbound_date', '2026-11-01')->firstOrFail()->nights());
    }

    public function test_admin_can_create_an_agency_with_custom_fee(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Paolo Gialli',
                'email' => 'paolo@gialliviaggi.test',
                'role' => 'agency',
                'password' => 'password123',
                'is_active' => true,
                'agency' => [
                    'fee_percent' => 12.5,
                    'company_name' => 'Gialli Viaggi',
                    'vat_number' => '55566677788',
                    'address' => 'Via Dante 1',
                    'city' => 'Firenze',
                    'zip_code' => '50100',
                    'province' => 'FI',
                    'phone' => '055 123456',
                    'email' => 'info@gialliviaggi.test',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'paolo@gialliviaggi.test')->firstOrFail();
        $this->assertTrue($user->isAgency());
        $this->assertSame(12.5, $user->agency->feePercent());
    }

    public function test_admin_can_register_deposit_and_cancel(): void
    {
        $booking = $this->createBooking();

        Livewire::test(ViewBooking::class, ['record' => $booking->id])->callAction('deposit');
        $this->assertSame(BookingStatus::DepositReceived, $booking->fresh()->status);

        $option = $this->createBooking(BookingAction::Option);
        Livewire::test(ViewBooking::class, ['record' => $option->id])->callAction('cancel');
        $this->assertSame(BookingStatus::Cancelled, $option->fresh()->status);
        $this->assertNotNull($option->fresh()->cancelled_at);
    }

    public function test_admin_can_update_booking_settings(): void
    {
        Livewire::test(Settings::class)
            ->fillForm(['option_hours_guest' => 12, 'default_agency_fee_percent' => 8, 'bank_iban' => 'IT60X0542811101000000654321'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(12, Setting::int('option_hours_guest'));
        $this->assertSame(8.0, $this->agencyUser()->agency->feePercent());
    }
}
