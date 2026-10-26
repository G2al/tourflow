<?php

use App\Models\Booking;
use App\Services\BookingDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Il backend non ha pagine pubbliche: la home porta al pannello admin.
Route::redirect('/', '/admin');

// PDF della pratica per gli amministratori (l'apertura qui NON conta come
// lettura della proforma da parte dell'agenzia).
Route::get('backoffice/bookings/{booking}/{document}', function (Request $request, Booking $booking, string $document, BookingDocuments $documents) {
    abort_unless($request->user()?->isAdmin(), 403);

    return $document === 'proforma' ? $documents->proforma($booking) : $documents->quote($booking);
})->middleware('auth')->whereIn('document', ['quote', 'proforma'])->name('admin.bookings.document');
