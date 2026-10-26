@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $passengers = $booking->passengers->keyBy('position');
@endphp
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>@yield('title') {{ $booking->code }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0; color: #0c4a6e; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; color: #0c4a6e; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; }
        th { text-align: left; background: #f1f5f9; font-size: 9.5px; text-transform: uppercase; color: #475569; }
        .lines td { border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .muted { color: #64748b; }
        .header td { padding: 0; }
        .box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; }
        .totals td { padding: 3px 6px; }
        .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #0c4a6e; }
        .highlight { background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 4px; padding: 10px 12px; margin-top: 14px; }
        .small { font-size: 9px; }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td>
            <h1>{{ $company['company_name'] }}</h1>
            <div class="muted">
                {{ $company['address'] }}<br>
                @if ($company['vat_number']) P.IVA {{ $company['vat_number'] }} · @endif
                {{ $company['contact_email'] }} @if ($company['contact_phone']) · {{ $company['contact_phone'] }} @endif
            </div>
        </td>
        <td class="right">
            <div style="font-size: 16px; font-weight: bold;">@yield('title')</div>
            <div>N. <strong>{{ $booking->code }}</strong></div>
            <div class="muted">Data: {{ now()->format('d/m/Y') }}</div>
            @yield('header-extra')
        </td>
    </tr>
</table>

<table style="margin-top: 16px;">
    <tr>
        <td style="width: 50%; padding-left: 0;">
            <div class="box">
                <strong>Referente</strong><br>
                {{ $booking->contactFullName() }}<br>
                {{ $booking->contact_email }} · {{ $booking->contact_phone }}
            </div>
        </td>
        <td style="width: 50%; padding-right: 0;">
            @if ($booking->agency)
                <div class="box">
                    <strong>Agenzia</strong><br>
                    {{ $booking->agency->company_name }} · P.IVA {{ $booking->agency->vat_number }}<br>
                    {{ $booking->agency->fullAddress() }}
                    @if ($booking->agency_reference)<br>Rif. pratica: {{ $booking->agency_reference }}@endif
                </div>
            @endif
        </td>
    </tr>
</table>

<h2>Servizi</h2>
@foreach ($booking->items as $item)
    <table class="lines" style="margin-bottom: 8px;">
        <tr>
            <th colspan="2">
                {{ $item->title }} — {{ $item->mode->getLabel() }}
                · {{ $item->start_date->format('d/m/Y') }}@if ($item->nights) - {{ $item->end_date->format('d/m/Y') }} ({{ $item->nights }} notti)@endif
            </th>
        </tr>
        @if ($item->flight)
            <tr><td colspan="2" class="muted">
                Volo da {{ $item->flight->departure_airport->getLabel() }}
                @if ($item->flight->carrier) · {{ $item->flight->carrier }} @endif
                @if ($item->flight->outbound_details) · Andata: {{ $item->flight->outbound_details }} @endif
                @if ($item->flight->return_details) · Ritorno: {{ $item->flight->return_details }} @endif
            </td></tr>
        @endif
        @foreach ($item->breakdown['rooms'] ?? [] as $room)
            <tr><td colspan="2"><strong>Camera {{ $room['index'] + 1 }}: {{ $room['room_type'] }}</strong>
                <span class="muted">— {{ collect($room['passengers'])->map(fn ($p) => $passengers[$p]?->fullName())->join(', ') }}</span></td></tr>
            @foreach ($room['lines'] as $line)
                <tr>
                    <td>{{ $line['label'] }}@if ($line['passenger'] !== null) <span class="muted">· {{ $passengers[$line['passenger']]?->fullName() }}</span>@endif</td>
                    <td class="right">{{ $money($line['amount']) }}</td>
                </tr>
            @endforeach
        @endforeach
        @foreach ($item->breakdown['lines'] ?? [] as $line)
            <tr>
                <td>{{ $line['label'] }}@if ($line['passenger'] !== null) <span class="muted">· {{ $passengers[$line['passenger']]?->fullName() }}</span>@endif</td>
                <td class="right">{{ $money($line['amount']) }}</td>
            </tr>
        @endforeach
        <tr><td class="right"><strong>Totale servizio</strong></td><td class="right" style="width: 90px;"><strong>{{ $money($item->total) }}</strong></td></tr>
    </table>
@endforeach

@if ($booking->insurances->isNotEmpty() || $booking->price_lock)
    <h2>Assicurazioni e garanzie</h2>
    <table class="lines">
        @foreach ($booking->insurances as $insurance)
            <tr><td>{{ $insurance->name }} ({{ $insurance->quantity }} × {{ $money($insurance->unit_price) }})</td><td class="right" style="width: 90px;">{{ $money($insurance->total) }}</td></tr>
        @endforeach
        @if ($booking->price_lock)
            <tr><td>Blocca prezzo</td><td class="right" style="width: 90px;">{{ $money($booking->price_lock_amount) }}</td></tr>
        @endif
    </table>
@endif

<h2>Passeggeri</h2>
<table class="lines">
    <tr><th>#</th><th>Nome</th><th>Data di nascita</th><th>Categoria</th><th>Documento</th></tr>
    @foreach ($booking->passengers as $passenger)
        <tr>
            <td>{{ $passenger->position + 1 }}</td>
            <td>{{ $passenger->fullName() }}@if ($passenger->is_lead) <span class="muted">(capogruppo)</span>@endif</td>
            <td>{{ $passenger->birth_date->format('d/m/Y') }}</td>
            <td>{{ $passenger->age_category->getLabel() }}</td>
            <td>{{ $passenger->document_number ? ($passenger->document_type?->getLabel().' '.$passenger->document_number) : '-' }}</td>
        </tr>
    @endforeach
</table>

<table class="totals" style="margin-top: 14px; width: 55%; margin-left: 45%;">
    <tr><td>Servizi</td><td class="right">{{ $money($booking->items_total) }}</td></tr>
    @if ((float) $booking->insurances_total > 0)<tr><td>Assicurazioni</td><td class="right">{{ $money($booking->insurances_total) }}</td></tr>@endif
    @if ($booking->price_lock)<tr><td>Blocca prezzo</td><td class="right">{{ $money($booking->price_lock_amount) }}</td></tr>@endif
    <tr class="grand"><td>Totale pratica</td><td class="right">{{ $money($booking->total) }}</td></tr>
    @if ($booking->agency)
        <tr><td class="muted">Fee agenzia ({{ rtrim(rtrim(number_format((float) $booking->fee_percent, 2, ',', '.'), '0'), ',') }}%)</td><td class="right muted">− {{ $money($booking->fee_amount) }}</td></tr>
        <tr><td><strong>Importo dovuto a {{ $company['company_name'] }}</strong></td><td class="right"><strong>{{ $money($booking->amount_due) }}</strong></td></tr>
    @endif
</table>

@yield('footer')
</body>
</html>
