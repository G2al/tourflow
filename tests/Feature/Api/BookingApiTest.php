<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Excursion;
use App\Models\Package;

class BookingApiTest extends ApiTestCase
{
    private const QUOTE_URL = '/api/v1/packages/hurghada-all-inclusive/quote';

    private function participants(): array
    {
        return [
            ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'birth_date' => '1985-03-10', 'gender' => 'M', 'tax_code' => 'BNCLCU85C10H501X'],
            ['first_name' => 'Anna', 'last_name' => 'Verdi', 'birth_date' => '1987-07-22', 'gender' => 'F'],
            ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'birth_date' => '2018-05-01', 'gender' => 'M'],
            ['first_name' => 'Sofia', 'last_name' => 'Bianchi', 'birth_date' => '2026-01-15', 'gender' => 'F'],
        ];
    }

    private function payload(array $overrides = []): array
    {
        $package = $this->hurghada();

        return [
            'package_id' => $package->id,
            'departure_date' => '2026-11-14',
            'contact' => ['first_name' => 'Luca', 'last_name' => 'Bianchi', 'email' => 'luca@example.com', 'phone' => '+39 333 2222222'],
            'participants' => $this->participants(),
            'excursion_ids' => [$package->excursions()->where('name', 'Safari in quad al tramonto')->value('excursions.id')],
            'insurance_ids' => [$package->insurances()->where('name', 'Medico-bagaglio Base')->value('insurances.id')],
            ...$overrides,
        ];
    }

    public function test_quote_applies_age_categories_fees_and_extras(): void
    {
        $data = $this->postJson(self::QUOTE_URL, $this->payload())
            ->assertOk()
            ->json('data');

        // Autunno: adulto 490, bambino 245, infant gratis; trasferimenti 15 a persona (infant esclusi).
        $this->assertSame(['adult', 'adult', 'child', 'infant'], array_column($data['participants'], 'age_category'));
        $this->assertEquals([505, 505, 260, 0], array_column($data['participants'], 'total'));
        // Quad 2×35 + 1×20, assicurazione 3×35.
        $this->assertEquals([90, 105], array_column($data['extras'], 'total'));
        $this->assertEquals(1465, $data['totals']['total']);
        $this->assertSame('2026-11-21', $data['return_date']);
    }

    public function test_agency_quote_uses_net_prices(): void
    {
        $data = $this->withToken($this->agencyToken())->postJson(self::QUOTE_URL, $this->payload())->json('data');

        // Adulti 415+12, bambino 210+12, quad 2×28+16, assicurazione 3×28.
        $this->assertSame('net', $data['price_type']);
        $this->assertEquals(1232, $data['totals']['total']);
    }

    public function test_single_adult_pays_single_supplement_and_third_adult_gets_extra_bed_price(): void
    {
        $single = $this->postJson(self::QUOTE_URL, ['departure_date' => '2026-11-14', 'participants' => [['birth_date' => '1980-01-01']]])->json('data');
        $this->assertEquals(490 + 145 + 15, $single['totals']['total']);

        $three = $this->postJson(self::QUOTE_URL, ['departure_date' => '2026-11-14', 'participants' => array_fill(0, 3, ['birth_date' => '1980-01-01'])])->json('data');
        $this->assertSame('Quota 3°/4° letto adulto', $three['participants'][2]['items'][0]['label']);
        $this->assertEquals(415, $three['participants'][2]['items'][0]['amount']);
    }

    public function test_per_service_products_have_a_single_price_for_the_group(): void
    {
        $data = $this->postJson('/api/v1/packages/transfer-privato-aeroporto-hurghada/quote', [
            'departure_date' => '2026-11-14',
            'participants' => array_fill(0, 3, ['birth_date' => '1980-01-01']),
        ])->assertOk()->json('data');

        $this->assertSame('per_service', $data['price_unit']);
        $this->assertEquals(30, $data['service']['amount']);
        $this->assertEquals([0, 0, 0], array_column($data['participants'], 'total'));
        $this->assertEquals(30, $data['totals']['total']);
        $this->assertSame('2026-11-14', $data['return_date']);
    }

    public function test_quote_rejects_dates_outside_validity_and_groups_without_adults(): void
    {
        $this->postJson(self::QUOTE_URL, ['departure_date' => '2028-01-01', 'participants' => [['birth_date' => '1980-01-01']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departure_date']);

        $this->postJson(self::QUOTE_URL, ['departure_date' => '2026-11-14', 'participants' => [['birth_date' => '2015-01-01']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participants']);
    }

    public function test_quote_rejects_extras_not_linked_to_the_package(): void
    {
        $sharmExcursion = Excursion::where('name', 'Ras Mohammed in barca')->value('id');

        $this->postJson(self::QUOTE_URL, $this->payload(['excursion_ids' => [$sharmExcursion]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['excursions']);
    }

    public function test_guest_booking_is_saved_with_gross_price_and_whatsapp_link(): void
    {
        $response = $this->postJson('/api/v1/bookings', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.price_type', 'gross')
            ->assertJsonPath('data.total', 1465)
            ->assertJsonCount(4, 'data.participants')
            ->assertJsonPath('data.participants.0.is_lead', true);

        $this->assertStringStartsWith('https://wa.me/393331234567?text=', $response->json('data.whatsapp_url'));
        $this->assertStringContainsString($response->json('data.code'), urldecode($response->json('data.whatsapp_url')));

        $booking = Booking::firstOrFail();
        $this->assertNull($booking->agency_id);
        $this->assertCount(2, $booking->extras);
    }

    public function test_excursion_product_can_be_booked(): void
    {
        $orangeBay = Package::where('slug', 'orange-bay-in-barca')->firstOrFail();

        $this->postJson('/api/v1/bookings', $this->payload([
            'package_id' => $orangeBay->id,
            'excursion_ids' => [],
            'insurance_ids' => [],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.departure_date', '2026-11-14')
            ->assertJsonPath('data.return_date', '2026-11-14')
            // Adulti 2×45 + bambino 25 (50% arrotondato ai 5 €) + infant gratis.
            ->assertJsonPath('data.total', 2 * 45 + 25);
    }

    public function test_agency_booking_uses_net_price_and_is_listed_for_the_agency(): void
    {
        $token = $this->agencyToken();

        $code = $this->withToken($token)
            ->postJson('/api/v1/bookings', $this->payload(['agency_reference' => 'PR-001']))
            ->assertCreated()
            ->assertJsonPath('data.price_type', 'net')
            ->assertJsonPath('data.total', 1232)
            ->assertJsonPath('data.agency_reference', 'PR-001')
            ->assertJsonPath('data.total_gross', 1465)
            ->json('data.code');

        $this->assertNotNull(Booking::where('code', $code)->value('agency_id'));

        $this->withToken($token)->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson("/api/v1/bookings/{$code}")->assertOk();
    }

    public function test_bookings_are_private(): void
    {
        $code = $this->postJson('/api/v1/bookings', $this->payload())->json('data.code');

        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->withToken($this->agencyToken())->getJson("/api/v1/bookings/{$code}")->assertNotFound();
    }

    public function test_booking_validation_messages_are_in_italian(): void
    {
        $errors = $this->postJson('/api/v1/bookings', ['package_id' => 999])
            ->assertUnprocessable()
            ->json('errors');

        $this->assertSame('Il valore selezionato per pacchetto non è valido.', $errors['package_id'][0]);
        $this->assertSame('Il campo email referente è obbligatorio.', $errors['contact.email'][0]);
    }
}
