<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF della pratica: preventivo e proforma (con istruzioni per il bonifico).
 */
class BookingDocuments
{
    public function quote(Booking $booking): Response
    {
        return $this->render('pdf.quote', $booking, "Preventivo-{$booking->code}.pdf");
    }

    public function proforma(Booking $booking): Response
    {
        return $this->render('pdf.proforma', $booking, "Proforma-{$booking->code}.pdf");
    }

    private function render(string $view, Booking $booking, string $filename): Response
    {
        $booking->loadMissing(['passengers', 'items.rooms.roomType', 'items.flight', 'insurances', 'agency']);

        return Pdf::loadView($view, [
            'booking' => $booking,
            'company' => Setting::values(),
        ])
            ->setPaper('a4')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->stream($filename);
    }
}
