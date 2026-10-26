<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\Response;

/**
 * PDF della pratica: preventivo e proforma (con istruzioni per il bonifico).
 */
class BookingDocuments
{
    public function quote(Booking $booking): Response
    {
        return $this->make('pdf.quote', $booking)->stream("Preventivo-{$booking->code}.pdf");
    }

    /**
     * Contenuto binario del preventivo (allegato email).
     */
    public function quoteOutput(Booking $booking): string
    {
        return $this->make('pdf.quote', $booking)->output();
    }

    public function proforma(Booking $booking): Response
    {
        return $this->make('pdf.proforma', $booking)->stream("Proforma-{$booking->code}.pdf");
    }

    private function make(string $view, Booking $booking): DomPdf
    {
        $booking->loadMissing(['passengers', 'items.rooms.roomType', 'items.flight', 'insurances', 'agency']);

        return Pdf::loadView($view, [
            'booking' => $booking,
            'company' => Setting::values(),
        ])
            ->setPaper('a4')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false]);
    }
}
