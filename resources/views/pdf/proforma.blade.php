@extends('pdf.layout')

@section('title', 'Proforma')

@section('header-extra')
    @if ($booking->confirmed_at)
        <div class="muted">Confermata il {{ $booking->confirmed_at->format('d/m/Y H:i') }}</div>
    @endif
@endsection

@section('footer')
    <div class="highlight">
        <strong>Modalità di pagamento: bonifico bancario</strong><br><br>
        Acconto del {{ rtrim(rtrim(number_format((float) $booking->deposit_percent, 2, ',', '.'), '0'), ',') }}% da versare per rendere definitiva la prenotazione:
        <strong style="font-size: 13px;">€ {{ number_format((float) $booking->deposit_amount, 2, ',', '.') }}</strong><br>
        Saldo: € {{ number_format((float) $booking->amount_due - (float) $booking->deposit_amount, 2, ',', '.') }}<br><br>
        @if ($company['bank_iban'])
            Beneficiario: <strong>{{ $company['bank_beneficiary'] ?: $company['company_name'] }}</strong><br>
            @if ($company['bank_name']) Banca: {{ $company['bank_name'] }}<br> @endif
            IBAN: <strong>{{ $company['bank_iban'] }}</strong><br>
        @endif
        Causale: <strong>Pratica {{ $booking->code }} - {{ $booking->contactFullName() }}</strong>
    </div>
    <p class="small muted" style="margin-top: 12px;">
        Documento proforma non valido ai fini fiscali. La fattura sarà emessa al ricevimento del pagamento.
    </p>
@endsection
