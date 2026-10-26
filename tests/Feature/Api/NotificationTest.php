<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Mail\AgencyRegisteredMail;
use App\Mail\BookingMail;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\Mail;

class NotificationTest extends ApiTestCase
{
    private const OPERATOR = 'operatore@queentour.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['tourflow.notification_email' => self::OPERATOR]);
        Mail::fake();
    }

    public function test_guest_quote_is_emailed_to_the_customer_with_the_pdf(): void
    {
        $this->postJson('/api/v1/bookings', $this->booking('quote'))->assertCreated();

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('luca@example.com')
            && $mail->event === 'quote'
            && count($mail->attachments()) === 1);
        // Nessun avviso al tour operator per i preventivi.
        Mail::assertNotSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo(self::OPERATOR));
    }

    public function test_guest_option_notifies_customer_and_operator(): void
    {
        $this->postJson('/api/v1/bookings', $this->booking('option'))->assertCreated();

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('luca@example.com') && $mail->event === 'option');
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo(self::OPERATOR) && $mail->audience === BookingMail::OPERATOR);
    }

    public function test_agency_confirmation_goes_to_agency_with_operator_in_copy(): void
    {
        $this->withToken($this->agencyToken())->postJson('/api/v1/bookings', $this->booking('confirm'))->assertCreated();

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('info@viaggisole.test')
            && $mail->hasCc('agenzia@tourflow.test')
            && $mail->event === 'confirmed'
            && str_contains($mail->render(), 'Apri la proforma'));
        // Il cliente finale dell'agenzia non riceve email.
        Mail::assertNotSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo('luca@example.com'));
        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->hasTo(self::OPERATOR) && $mail->event === 'confirmed');
    }

    public function test_opening_the_proforma_notifies_the_operator_once(): void
    {
        $url = $this->withToken($this->agencyToken())->postJson('/api/v1/bookings', $this->booking('confirm'))->json('data.documents.proformaUrl');

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->event === 'proforma_viewed', 1);
    }

    public function test_option_reminders_are_sent_once_per_threshold(): void
    {
        $code = $this->withToken($this->agencyToken())->postJson('/api/v1/bookings', $this->booking('option'))->json('data.code');
        $service = app(BookingService::class);

        $this->assertSame(0, $service->sendOptionReminders());

        $this->travel(49)->hours(); // mancano 23 ore
        $this->assertSame(1, $service->sendOptionReminders());
        $this->assertSame(0, $service->sendOptionReminders());

        $this->travel(22 * 60 + 30)->minutes(); // mancano 30 minuti
        $this->assertSame(1, $service->sendOptionReminders());

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->event === 'option_reminder', 2);
        $this->assertSame([24, 1], Booking::where('code', $code)->first()->option_reminders_sent);
    }

    public function test_expired_options_are_notified(): void
    {
        $this->postJson('/api/v1/bookings', $this->booking('option'))->assertCreated();

        $this->travel(7)->hours();
        $this->artisan('bookings:expire-options')->assertSuccessful();
        $this->artisan('bookings:expire-options')->assertSuccessful();

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->event === 'option_expired' && $mail->hasTo('luca@example.com'), 1);
    }

    public function test_status_changes_from_the_panel_are_notified(): void
    {
        $code = $this->postJson('/api/v1/bookings', $this->booking('confirm'))->json('data.code');

        app(BookingService::class)->changeStatus(Booking::where('code', $code)->first(), BookingStatus::DepositReceived);

        Mail::assertSent(BookingMail::class, fn (BookingMail $mail) => $mail->event === 'deposit_received' && $mail->hasTo('luca@example.com'));
    }

    public function test_agency_registration_sends_welcome_and_operator_notice(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Giulia Neri',
            'email' => 'giulia@neriviaggi.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'agency' => [
                'company_name' => 'Neri Viaggi', 'vat_number' => '11122233344', 'address' => 'Via Po 3',
                'city' => 'Torino', 'zip_code' => '10100', 'province' => 'TO',
                'phone' => '011 123', 'email' => 'info@neriviaggi.test',
            ],
        ])->assertCreated();

        Mail::assertSent(AgencyRegisteredMail::class, fn ($mail) => $mail->hasTo('info@neriviaggi.test') && $mail->hasCc('giulia@neriviaggi.test'));
        Mail::assertSent(AgencyRegisteredMail::class, fn ($mail) => $mail->hasTo(self::OPERATOR));
    }

    public function test_every_template_renders(): void
    {
        $guest = Booking::where('code', $this->postJson('/api/v1/bookings', $this->booking('option'))->json('data.code'))->first();
        $agency = Booking::where('code', $this->withToken($this->agencyToken())->postJson('/api/v1/bookings', $this->booking('confirm'))->json('data.code'))->first();

        foreach (['quote', 'option', 'option_reminder', 'option_expired', 'confirmed', 'deposit_received', 'paid', 'cancelled'] as $event) {
            foreach ([$guest, $agency] as $booking) {
                $html = (new BookingMail($booking, $event, hoursLeft: 1))->render();
                $this->assertStringContainsString($booking->code, $html);
            }
        }

        foreach (['option', 'confirmed', 'proforma_viewed'] as $event) {
            $this->assertStringContainsString('Apri nel pannello', (new BookingMail($agency, $event, BookingMail::OPERATOR))->render());
        }

        $this->assertStringContainsString('Benvenuto', (new AgencyRegisteredMail($agency->agency))->render());
    }
}
