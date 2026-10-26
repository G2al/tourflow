@extends('pdf.layout')

@section('title', 'Preventivo')

@section('header-extra')
    @if ($booking->quote_valid_until)
        <div class="muted">Valido fino al {{ $booking->quote_valid_until->format('d/m/Y') }}</div>
    @endif
@endsection

@section('footer')
    <div class="highlight">
        Questo preventivo <strong>non blocca la disponibilità</strong>.
        @if ($booking->price_lock)
            Il prezzo è garantito grazie all'opzione "Blocca prezzo".
        @else
            Prezzi e disponibilità saranno verificati al momento della conferma.
        @endif
    </div>
@endsection
