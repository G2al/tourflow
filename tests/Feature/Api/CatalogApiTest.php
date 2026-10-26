<?php

namespace Tests\Feature\Api;

use App\Models\Package;

class CatalogApiTest extends ApiTestCase
{
    /** Campi del contratto TravelPackage definito dal frontend. */
    private const CONTRACT = [
        'slug', 'title', 'destination', 'country', 'location', 'image', 'type', 'price', 'days', 'nights',
        'period', 'months', 'departures', 'board', 'stay', 'badge', 'description', 'longDescription',
        'highlights', 'included', 'excluded', 'itinerary',
    ];

    public function test_everyone_sees_the_public_price(): void
    {
        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.title', 'Hurghada All Inclusive')
            ->assertJsonPath('data.0.type', 'Soggiorno')
            // 70 € a notte × 7 notti consigliate
            ->assertJsonPath('data.0.price', 490)
            ->assertJsonPath('data.0.pricePerNight', 70)
            ->assertJsonPath('data.0.bookingModes', ['hotel_only', 'flight_hotel'])
            ->assertJsonMissingPath('data.0.agencyFee');
    }

    public function test_agency_sees_the_same_price_plus_its_fee(): void
    {
        $this->withToken($this->agencyToken())
            ->getJson('/api/v1/packages/hurghada-all-inclusive')
            ->assertOk()
            ->assertJsonPath('data.price', 490)
            ->assertJsonPath('data.agencyFee', ['percent' => 10, 'amount' => 49, 'netPrice' => 441]);
    }

    public function test_package_detail_matches_the_frontend_contract_and_exposes_booking_data(): void
    {
        $data = $this->getJson('/api/v1/packages/hurghada-all-inclusive')->assertOk()->json('data');

        foreach (self::CONTRACT as $field) {
            $this->assertArrayHasKey($field, $data, "Campo mancante: {$field}");
        }

        $this->assertSame(['Standard', 'Superior vista mare', 'Deluxe vista mare'], array_column($data['hotel']['roomTypes'], 'name'));
        $this->assertEquals(30, $data['hotel']['roomTypes'][2]['supplementPerNight']);
        $this->assertCount(32, $data['flights']); // 8 settimane × 4 aeroporti
        // Ordinati per data di partenza e aeroporto.
        $this->assertSame('2026-10-18', $data['flights'][0]['outboundDate']);
        $this->assertSame(['BRI', 'FCO', 'MXP', 'NAP'], array_column(array_slice($data['flights'], 0, 4), 'departureAirport'));
        $this->assertSame(30, $data['flights'][0]['seatsAvailable']);
        $this->assertSame(['infant' => ['min' => 0, 'max' => 1], 'child' => ['min' => 2, 'max' => 17], 'adult' => ['min' => 18, 'max' => null]], $data['ageRanges']);
        $this->assertSame(3, $data['minNights']);
        $this->assertEquals(70, $data['periods'][0]['adultPrice']);
    }

    public function test_excursions_and_transfers_are_standalone_products(): void
    {
        $this->getJson('/api/v1/packages/orange-bay-in-barca')
            ->assertOk()
            ->assertJsonPath('data.type', 'Escursione')
            ->assertJsonPath('data.bookingModes', ['excursion'])
            ->assertJsonPath('data.days', 1)
            ->assertJsonPath('data.price', 45);

        $this->getJson('/api/v1/packages/transfer-privato-aeroporto-hurghada')
            ->assertOk()
            ->assertJsonPath('data.type', 'Transfer')
            ->assertJsonPath('data.priceUnit', 'per_service')
            ->assertJsonPath('data.price', 30)
            ->assertJsonPath('data.flights', []);
    }

    public function test_packages_can_be_filtered_and_sorted(): void
    {
        $count = fn (string $query) => count($this->getJson("/api/v1/packages?{$query}")->assertOk()->json('data'));

        $this->assertSame(2, $count('type=Escursione'));
        $this->assertSame(3, $count('type=Soggiorno'));
        $this->assertSame(4, $count('destination=hurghada'));
        $this->assertSame(4, $count('days=1'));
        $this->assertSame(2, $count('board='.urlencode('All Inclusive')));
        $this->assertSame(3, $count('max_price=50'));
        $this->assertSame(1, $count('min_price=550'));

        $this->getJson('/api/v1/packages?sort=price_asc')->assertJsonPath('data.0.slug', 'transfer-privato-aeroporto-hurghada');
        $this->getJson('/api/v1/packages?sort=price_desc')->assertJsonPath('data.0.slug', 'sharm-el-sheikh-resort-spa');
        $this->getJson('/api/v1/packages?type=Crociera')->assertUnprocessable();
    }

    public function test_months_are_derived_from_price_periods(): void
    {
        $package = Package::where('slug', 'sharm-el-sheikh-resort-spa')->firstOrFail();
        $package->update(['period_label' => null]);
        $package->periods()->where('name', '!=', 'Autunno')->delete();

        $this->getJson('/api/v1/packages/sharm-el-sheikh-resort-spa')
            ->assertJsonPath('data.period', 'Ottobre - Dicembre')
            ->assertJsonPath('data.months', ['Ottobre', 'Novembre', 'Dicembre']);
    }

    public function test_catalog_support_endpoints(): void
    {
        $this->getJson('/api/v1/packages/filters')
            ->assertOk()
            ->assertJsonPath('data.types', ['Soggiorno', 'Transfer', 'Escursione']);

        $this->getJson('/api/v1/insurances')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.pricePerPerson', 29);

        $this->getJson('/api/v1/legal-pages')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.requiresAcceptance', true);

        $this->getJson('/api/v1/legal-pages/privacy-policy')->assertOk()->assertJsonPath('data.title', 'Privacy policy');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.option_hours_agency', '72')
            ->assertJsonPath('data.option_hours_guest', '6')
            ->assertJsonPath('data.price_lock_amount', '49')
            ->assertJsonMissingPath('data.bank_iban');

        $this->getJson('/api/v1/destinations')->assertOk()->assertJsonPath('data.0.packagesCount', 4);
    }
}
