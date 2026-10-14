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

    public function test_guest_sees_published_packages_with_gross_prices(): void
    {
        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.title', 'Hurghada All Inclusive')
            ->assertJsonPath('data.0.type', 'Soggiorno')
            ->assertJsonPath('data.0.priceType', 'gross')
            ->assertJsonPath('data.0.price', 490)
            ->assertJsonMissingPath('data.0.priceGross');
    }

    public function test_package_matches_the_frontend_contract(): void
    {
        $data = $this->getJson('/api/v1/packages/hurghada-all-inclusive')->assertOk()->json('data');

        foreach (self::CONTRACT as $field) {
            $this->assertArrayHasKey($field, $data, "Campo mancante: {$field}");
        }

        $this->assertSame('Hurghada', $data['destination']);
        $this->assertSame('Egitto', $data['country']);
        $this->assertSame('Hurghada · Mar Rosso', $data['location']);
        $this->assertSame(8, $data['days']);
        $this->assertSame(7, $data['nights']);
        $this->assertSame("Tutto l'anno", $data['period']);
        $this->assertCount(12, $data['months']);
        $this->assertSame('Gennaio', $data['months'][0]);
        $this->assertSame(['Aeroporto di Hurghada'], $data['departures']);
        $this->assertSame('All Inclusive', $data['board']);
        $this->assertSame('Resort 5 stelle', $data['stay']);
        $this->assertSame('Più richiesto', $data['badge']);
        $this->assertCount(4, $data['highlights']);
        $this->assertSame(['day', 'title', 'description'], array_keys($data['itinerary'][0]));
        $this->assertContains("Volo A/R dall'Italia", $data['excluded']);

        // Aggiunte del backend nel dettaglio.
        $this->assertSame('per_person', $data['priceUnit']);
        $this->assertNotEmpty($data['periods']);
        $this->assertNotEmpty($data['excursions']);
        $this->assertNotEmpty($data['insurances']);
    }

    public function test_excursions_and_transfers_are_standalone_products(): void
    {
        $this->getJson('/api/v1/packages/orange-bay-in-barca')
            ->assertOk()
            ->assertJsonPath('data.type', 'Escursione')
            ->assertJsonPath('data.days', 1)
            ->assertJsonPath('data.nights', 0)
            ->assertJsonPath('data.board', 'Pranzo incluso');

        $this->getJson('/api/v1/packages/transfer-privato-aeroporto-hurghada')
            ->assertOk()
            ->assertJsonPath('data.type', 'Transfer')
            ->assertJsonPath('data.priceUnit', 'per_service')
            ->assertJsonPath('data.price', 30)
            ->assertJsonPath('data.badge', null)
            ->assertJsonPath('data.itinerary', []);
    }

    public function test_agency_sees_net_and_gross_prices(): void
    {
        $this->withToken($this->agencyToken())
            ->getJson('/api/v1/packages/hurghada-all-inclusive')
            ->assertOk()
            ->assertJsonPath('data.priceType', 'net')
            ->assertJsonPath('data.price', 415)
            ->assertJsonPath('data.priceGross', 490)
            ->assertJsonPath('data.periods.0.adultPrice', 415)
            ->assertJsonPath('data.periods.0.adultPriceGross', 490);
    }

    public function test_packages_can_be_filtered(): void
    {
        $count = fn (string $query) => count($this->getJson("/api/v1/packages?{$query}")->assertOk()->json('data'));

        $this->assertSame(2, $count('type=Escursione'));
        $this->assertSame(2, $count('type=transfer'));
        $this->assertSame(3, $count('type=Soggiorno'));
        $this->assertSame(4, $count('destination=hurghada'));
        $this->assertSame(4, $count('destination=Hurghada'));
        $this->assertSame(4, $count('days=1'));
        $this->assertSame(3, $count('min_days=8'));
        $this->assertSame(2, $count('departure='.urlencode('Aeroporto di Hurghada')));
        $this->assertSame(2, $count('board='.urlencode('All Inclusive')));
        $this->assertSame(3, $count('max_price=50'));
        $this->assertSame(1, $count('search=luxor'));

        $this->getJson('/api/v1/packages?type=Crociera')->assertUnprocessable();
    }

    public function test_packages_can_be_sorted(): void
    {
        $this->getJson('/api/v1/packages?sort=price_asc')
            ->assertJsonPath('data.0.slug', 'transfer-privato-aeroporto-hurghada');

        $this->getJson('/api/v1/packages?sort=price_desc')
            ->assertJsonPath('data.0.slug', 'sharm-el-sheikh-resort-spa');

        $this->getJson('/api/v1/packages?sort=duration')
            ->assertJsonPath('data.0.nights', 0)
            ->assertJsonPath('data.6.nights', 7);
    }

    public function test_period_and_months_are_derived_from_price_periods(): void
    {
        $package = Package::where('slug', 'sharm-el-sheikh-resort-spa')->firstOrFail();
        $package->periods()->where('name', '!=', 'Autunno')->delete();

        $this->getJson('/api/v1/packages/sharm-el-sheikh-resort-spa')
            ->assertJsonPath('data.period', 'Ottobre - Dicembre')
            ->assertJsonPath('data.months', ['Ottobre', 'Novembre', 'Dicembre']);
    }

    public function test_inactive_package_is_hidden(): void
    {
        $this->hurghada()->update(['is_active' => false]);

        $this->getJson('/api/v1/packages')->assertJsonCount(6, 'data');
        $this->getJson('/api/v1/packages/hurghada-all-inclusive')->assertNotFound();
    }

    public function test_filter_options_endpoint(): void
    {
        $this->getJson('/api/v1/packages/filters')
            ->assertOk()
            ->assertJsonPath('data.types', ['Soggiorno', 'Transfer', 'Escursione'])
            ->assertJsonPath('data.destinations.0.name', 'Hurghada')
            ->assertJsonPath('data.days', [1, 8])
            ->assertJsonPath('data.priceRange', ['min' => 30, 'max' => 590])
            ->assertJsonFragment(['boards' => ['All Inclusive', 'Mezza pensione', 'Pranzo incluso', 'Servizio privato']]);
    }

    public function test_destinations_and_settings(): void
    {
        $this->getJson('/api/v1/destinations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Hurghada')
            ->assertJsonPath('data.0.packagesCount', 4);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', '393331234567');
    }
}
