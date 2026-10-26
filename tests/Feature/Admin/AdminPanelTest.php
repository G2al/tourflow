<?php

namespace Tests\Feature\Admin;

use App\Models\Destination;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Insurance;
use App\Models\LegalPage;
use App\Models\Package;

class AdminPanelTest extends AdminTestCase
{
    public function test_agency_users_cannot_access_the_panel(): void
    {
        $this->actingAs($this->agencyUser())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_every_admin_page_renders(): void
    {
        $booking = $this->createBooking();
        $package = Package::firstOrFail();

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
            '/admin/flights',
            '/admin/flights/create',
            '/admin/flights/'.Flight::first()->id.'/edit',
            '/admin/insurances',
            '/admin/insurances/'.Insurance::first()->id.'/edit',
            '/admin/legal-pages',
            '/admin/legal-pages/'.LegalPage::first()->id.'/edit',
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.$this->agencyUser()->id.'/edit',
            '/admin/settings',
        ];

        $this->actingAs($this->admin());

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_booking_page_shows_services_passengers_and_agency(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->admin())
            ->get("/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertSee($booking->code)
            ->assertSee('Hurghada All Inclusive')
            ->assertSee('Volo + hotel')
            ->assertSee('BNCLCU85C10H501X')
            ->assertSee('Viaggi Sole Srl')
            ->assertSee('Proforma inviata');
    }

    public function test_admin_can_open_documents_without_marking_the_proforma_as_read(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->admin());
        $this->get(route('admin.bookings.document', [$booking, 'quote']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('admin.bookings.document', [$booking, 'proforma']))->assertOk();

        $this->assertNull($booking->fresh()->proforma_viewed_at);
    }

    public function test_documents_are_admin_only(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->agencyUser())
            ->get(route('admin.bookings.document', [$booking, 'proforma']))
            ->assertForbidden();
    }
}
