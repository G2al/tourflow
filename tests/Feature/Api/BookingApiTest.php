<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Support\Facades\URL;

class BookingApiTest extends ApiTestCase
{
    // ---------------------------------------------------------------
    // Prezzi (carrello)
    // ---------------------------------------------------------------

    public function test_hotel_only_quote_prices_every_night_and_passenger(): void
    {
        $data = $this->postJson('/api/v1/cart/quote', $this->hotelOnlyCart())->assertOk()->json('data');

        // 7 notti a 70 €: adulti 490, bambino (35/notte) 245, infant gratis; trasferimenti 15 a persona.
        $item = $data['items'][0];
        $this->assertSame(7, $item['nights']);
        $this->assertEquals(1225, $item['rooms'][0]['total']);
        $this->assertEquals(1270, $item['total']);
        $this->assertSame(['adult', 'adult', 'child', 'infant'], array_column($data['passengers'], 'ageCategory'));
        // Assicurazione 29 × 3 paganti.
        $this->assertEquals(87, $data['insurances'][0]['total']);
        $this->assertEquals(1357, $data['totals']['total']);
        $this->assertNull($data['totals']['feePercent']);
        $this->assertEquals(1357, $data['totals']['amountDue']);
        $this->assertEquals(407.1, $data['totals']['depositAmount']);
        $this->assertTrue($data['available']);
    }

    public function test_agency_quote_applies_the_fee_on_the_whole_booking(): void
    {
        $data = $this->withToken($this->agencyToken())
            ->postJson('/api/v1/cart/quote', $this->hotelOnlyCart(['price_lock' => true]))
            ->json('data');

        // 1357 + blocca prezzo 49 = 1406; fee 10% = 140,60.
        $this->assertEquals(1406, $data['totals']['total']);
        $this->assertEquals(10, $data['totals']['feePercent']);
        $this->assertEquals(140.6, $data['totals']['feeAmount']);
        $this->assertEquals(1265.4, $data['totals']['amountDue']);
        $this->assertEquals(379.62, $data['totals']['depositAmount']);
    }

    public function test_room_type_supplement_single_supplement_and_multiple_rooms(): void
    {
        $cart = $this->hotelOnlyCart([
            'passengers' => array_slice($this->family(), 0, 2),
            'insurance_ids' => [],
        ]);
        $cart['items'][0]['rooms'] = [
            ['room_type_id' => $this->roomType('Deluxe vista mare')->id, 'passengers' => [0]],
            ['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [1]],
        ];

        $item = $this->postJson('/api/v1/cart/quote', $cart)->assertOk()->json('data.items.0');

        // Deluxe da solo: 490 + supplemento 30×7 = 210 + singola 21×7 = 147.
        $this->assertEquals(847, $item['rooms'][0]['total']);
        // Standard da solo: 490 + singola 147.
        $this->assertEquals(637, $item['rooms'][1]['total']);
        $this->assertEquals(847 + 637 + 2 * 15, $item['total']);
    }

    public function test_flight_plus_hotel_uses_flight_dates_and_prices(): void
    {
        $flight = $this->flight('NAP', '2026-10-18');
        $data = $this->postJson('/api/v1/cart/quote', [
            'passengers' => array_slice($this->family(), 0, 2),
            'items' => [[
                'package_id' => $this->hurghada()->id,
                'mode' => 'flight_hotel',
                'flight_id' => $flight->id,
                'rooms' => [['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [0, 1]]],
            ]],
        ])->assertOk()->json('data');

        $item = $data['items'][0];
        $this->assertSame('2026-10-18', $item['startDate']);
        $this->assertSame('2026-10-25', $item['endDate']);
        $this->assertSame(2, $item['flight']['seats']);
        // Hotel 2 × 490 + volo 2 × 290 + trasferimenti 2 × 15.
        $this->assertEquals(980 + 580 + 30, $data['totals']['total']);
    }

    public function test_cart_with_several_products(): void
    {
        $cart = $this->hotelOnlyCart(['insurance_ids' => []]);
        $cart['items'][] = ['package_id' => Package::where('slug', 'orange-bay-in-barca')->value('id'), 'date' => '2026-11-16', 'passengers' => [0, 1, 2, 3]];
        $cart['items'][] = ['package_id' => Package::where('slug', 'transfer-privato-aeroporto-hurghada')->value('id'), 'date' => '2026-11-14', 'passengers' => [0, 1, 2, 3]];

        $data = $this->postJson('/api/v1/cart/quote', $cart)->assertOk()->json('data');

        // Orange Bay: 2 × 45 + bambino 23 + infant 0; transfer a servizio 30.
        $this->assertEquals(113, $data['items'][1]['total']);
        $this->assertEquals(30, $data['items'][2]['total']);
        $this->assertEquals(1270 + 113 + 30, $data['totals']['total']);
    }

    public function test_cart_validation_rules(): void
    {
        // Minimo notti
        $cart = $this->hotelOnlyCart();
        $cart['items'][0]['check_out'] = '2026-11-16';
        $this->postJson('/api/v1/cart/quote', $cart)->assertUnprocessable()->assertJsonValidationErrors(['items.0.check_out']);

        // Camera senza adulti
        $cart = $this->hotelOnlyCart();
        $cart['items'][0]['rooms'] = [
            ['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [0, 1]],
            ['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [2, 3]],
        ];
        $this->postJson('/api/v1/cart/quote', $cart)->assertUnprocessable()->assertJsonValidationErrors(['items.0.rooms.1.passengers']);

        // Passeggero non assegnato
        $cart = $this->hotelOnlyCart();
        $cart['items'][0]['rooms'][0]['passengers'] = [0, 1, 2];
        $this->postJson('/api/v1/cart/quote', $cart)->assertUnprocessable()->assertJsonValidationErrors(['passengers']);

        // Tipologia di camera di un altro hotel
        $cart = $this->hotelOnlyCart();
        $cart['items'][0]['rooms'][0]['room_type_id'] = $this->roomType('Standard', 'sharm-el-sheikh-resort-spa')->id;
        $this->postJson('/api/v1/cart/quote', $cart)->assertUnprocessable()->assertJsonValidationErrors(['items.0.rooms.0.room_type_id']);
    }

    // ---------------------------------------------------------------
    // Pratiche: preventivo, opzione, conferma
    // ---------------------------------------------------------------

    public function test_guest_quote_does_not_need_names_or_terms_and_is_reachable_by_link(): void
    {
        $response = $this->postJson('/api/v1/bookings', $this->booking('quote', $this->hotelOnlyCart(['passengers' => $this->family(false)])))
            ->assertCreated()
            ->assertJsonPath('data.status', 'quote')
            ->assertJsonPath('data.quoteValidUntil', '2026-10-16')
            ->assertJsonPath('data.actions.canOption', true)
            ->assertJsonPath('data.totals.total', 1357);

        $code = $response->json('data.code');
        $token = $response->json('data.accessToken');

        $this->getJson("/api/v1/bookings/{$code}")->assertNotFound();
        $this->getJson("/api/v1/bookings/{$code}?token={$token}")->assertOk();
        $this->withHeader('X-Booking-Token', $token)->getJson("/api/v1/bookings/{$code}")->assertOk();

        $this->get($response->json('data.documents.quotePdfUrl'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_option_requires_terms_and_complete_passengers(): void
    {
        $this->postJson('/api/v1/bookings', $this->booking('option', extra: ['terms_accepted' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terms_accepted']);

        $this->postJson('/api/v1/bookings', $this->booking('option', $this->hotelOnlyCart(['passengers' => $this->family(false)])))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['passengers.0.first_name', 'passengers.3.gender']);
    }

    public function test_guest_option_lasts_6_hours_and_then_expires(): void
    {
        $response = $this->postJson('/api/v1/bookings', $this->booking('option'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'option');

        $booking = Booking::where('code', $response->json('data.code'))->firstOrFail();
        $this->assertTrue($booking->option_expires_at->equalTo(now()->addHours(6)));
        $this->assertNotNull($booking->terms_accepted_at);
        $this->assertCount(3, $booking->accepted_legal_pages);

        $this->travel(7)->hours();
        $this->artisan('bookings:expire-options')->assertSuccessful();

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->postJson("/api/v1/bookings/{$booking->code}/confirm?token={$booking->access_token}", ['terms_accepted' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_agency_option_lasts_72_hours(): void
    {
        $code = $this->withToken($this->agencyToken())->postJson('/api/v1/bookings', $this->booking('option'))->json('data.code');

        $this->assertTrue(Booking::where('code', $code)->value('option_expires_at')->equalTo(now()->addHours(72)));
    }

    public function test_options_and_confirmations_hold_inventory_and_prevent_overbooking(): void
    {
        $deluxe = $this->roomType('Deluxe vista mare'); // 4 camere a notte
        $cart = $this->hotelOnlyCart(['passengers' => array_map(fn ($i) => ['first_name' => "Ospite{$i}", 'last_name' => 'Test', 'birth_date' => '1980-01-01', 'gender' => 'M'], range(0, 3)), 'insurance_ids' => []]);
        $cart['items'][0]['rooms'] = array_map(fn ($i) => ['room_type_id' => $deluxe->id, 'passengers' => [$i]], range(0, 3));

        $this->postJson('/api/v1/bookings', $this->booking('option', $cart))->assertCreated();

        // Le 4 Deluxe sono occupate anche solo per una notte in comune.
        $other = $this->hotelOnlyCart(['passengers' => [['first_name' => 'Paolo', 'last_name' => 'Neri', 'birth_date' => '1980-01-01', 'gender' => 'M']], 'insurance_ids' => []]);
        $other['items'][0]['check_in'] = '2026-11-20';
        $other['items'][0]['check_out'] = '2026-11-25';
        $other['items'][0]['rooms'] = [['room_type_id' => $deluxe->id, 'passengers' => [0]]];

        $this->postJson('/api/v1/cart/quote', $other)->assertOk()->assertJsonPath('data.available', false);
        $this->postJson('/api/v1/bookings', $this->booking('confirm', $other))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.rooms.0']);

        // Un preventivo non blocca nulla.
        $this->postJson('/api/v1/bookings', $this->booking('quote', $other))->assertCreated();

        // Scaduta l'opzione la disponibilità torna libera.
        $this->travel(7)->hours();
        $this->postJson('/api/v1/bookings', $this->booking('confirm', $other))->assertCreated();
    }

    public function test_flight_seats_are_limited(): void
    {
        $flight = $this->flight();
        $flight->update(['seats' => 2]);

        $cart = [
            'passengers' => $this->family(),
            'items' => [[
                'package_id' => $this->hurghada()->id,
                'mode' => 'flight_hotel',
                'flight_id' => $flight->id,
                'rooms' => [['room_type_id' => $this->roomType('Standard')->id, 'passengers' => [0, 1, 2, 3]]],
            ]],
        ];

        // 3 posti (l'infant non occupa posto) su 2 disponibili.
        $this->postJson('/api/v1/bookings', $this->booking('confirm', $cart))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.flight']);
    }

    public function test_guest_confirmation_is_final(): void
    {
        $this->postJson('/api/v1/bookings', $this->booking('confirm'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.documents.proformaUrl', null)
            ->assertJsonPath('data.actions.canCancel', false);
    }

    public function test_agency_confirmation_generates_a_tracked_proforma(): void
    {
        $token = $this->agencyToken();
        $response = $this->withToken($token)
            ->postJson('/api/v1/bookings', $this->booking('confirm', extra: ['agency_reference' => 'PR-001']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'proforma_sent')
            ->assertJsonPath('data.isAgencyBooking', true)
            ->assertJsonPath('data.agencyReference', 'PR-001')
            ->assertJsonPath('data.totals.amountDue', 1221.3)
            ->assertJsonPath('data.totals.depositAmount', 366.39);

        $booking = Booking::where('code', $response->json('data.code'))->firstOrFail();
        $this->assertNull($booking->proforma_viewed_at);

        // Un link manomesso viene rifiutato e non conta come lettura.
        $proformaUrl = $response->json('data.documents.proformaUrl');
        $this->get(str_replace('signature=', 'signature=x', $proformaUrl))->assertForbidden();

        $this->get($proformaUrl)->assertOk()->assertHeader('content-type', 'application/pdf');

        $booking->refresh();
        $this->assertSame(BookingStatus::ProformaViewed, $booking->status);
        $this->assertNotNull($booking->proforma_viewed_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_quote_can_become_option_then_confirmation_with_names_added_later(): void
    {
        $quote = $this->postJson('/api/v1/bookings', $this->booking('quote', $this->hotelOnlyCart(['passengers' => $this->family(false)])))->json('data');
        $url = "/api/v1/bookings/{$quote['code']}";
        $token = "?token={$quote['accessToken']}";

        $this->postJson("{$url}/option{$token}", ['terms_accepted' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['passengers.0.first_name']);

        $this->postJson("{$url}/option{$token}", ['terms_accepted' => true, 'passengers' => $this->family()])
            ->assertOk()
            ->assertJsonPath('data.status', 'option')
            ->assertJsonPath('data.passengers.0.firstName', 'Luca');

        $this->postJson("{$url}/confirm{$token}", ['terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_price_is_recalculated_unless_price_lock_was_bought(): void
    {
        $unlocked = $this->postJson('/api/v1/bookings', $this->booking('quote'))->json('data');
        $locked = $this->postJson('/api/v1/bookings', $this->booking('quote', $this->hotelOnlyCart(['price_lock' => true])))->json('data');

        // Il listino aumenta dopo il preventivo.
        $this->hurghada()->periods()->where('name', 'Autunno')->update(['adult_price' => 80, 'child_price' => 40]);

        $this->postJson("/api/v1/bookings/{$unlocked['code']}/confirm?token={$unlocked['accessToken']}", ['terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.totals.total', 1357 + 7 * 10 * 2 + 7 * 5);

        $this->postJson("/api/v1/bookings/{$locked['code']}/confirm?token={$locked['accessToken']}", ['terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('data.totals.total', 1357 + 49);
    }

    public function test_quotes_and_options_can_be_cancelled_online(): void
    {
        $quote = $this->postJson('/api/v1/bookings', $this->booking('quote'))->json('data');
        $this->postJson("/api/v1/bookings/{$quote['code']}/cancel?token={$quote['accessToken']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $confirmed = $this->postJson('/api/v1/bookings', $this->booking('confirm'))->json('data');
        $this->postJson("/api/v1/bookings/{$confirmed['code']}/cancel?token={$confirmed['accessToken']}")
            ->assertUnprocessable();
    }

    public function test_bookings_of_other_agencies_are_private(): void
    {
        $code = $this->postJson('/api/v1/bookings', $this->booking('quote'))->json('data.code');

        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->withToken($this->agencyToken())->getJson("/api/v1/bookings/{$code}")->assertNotFound();
        // Un preventivo di un cliente non ha proforma, anche con un link firmato valido.
        $this->get(URL::signedRoute('api.bookings.proforma', ['booking' => $code]))->assertNotFound();
    }

    public function test_validation_messages_are_in_italian(): void
    {
        $errors = $this->postJson('/api/v1/bookings', ['action' => 'option'])->assertUnprocessable()->json('errors');

        $this->assertSame('Il campo email referente è obbligatorio.', $errors['contact.email'][0]);
        $this->assertSame('Il campo accettazione delle condizioni è obbligatorio.', $errors['terms_accepted'][0]);
    }
}
