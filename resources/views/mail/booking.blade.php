@php
    $money = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    $company = \App\Models\Setting::get('company_name');
@endphp
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('tourflow.frontend_url')">
{{ $company }}
</x-mail::header>
</x-slot:header>

# {{ $headline }}

{{ $intro }}

<x-mail::panel>
**Pratica:** {{ $booking->code }} · {{ $booking->status->getLabel() }}

@foreach ($booking->items as $item)
**{{ $item->title }}** — {{ $item->mode->getLabel() }}<br>
{{ $item->start_date->format('d/m/Y') }}@if ($item->nights) – {{ $item->end_date->format('d/m/Y') }} ({{ $item->nights }} notti)@endif

@endforeach
**Passeggeri:** {{ $booking->passengers->count() }} · capogruppo {{ $booking->leadPassenger()?->fullName() }}
</x-mail::panel>

<x-mail::table>
| | |
|:--|--:|
| Totale pratica | **{{ $money($booking->total) }}** |
@if ($forAgency)
| Fee agenzia ({{ rtrim(rtrim(number_format((float) $booking->fee_percent, 2, ',', ''), '0'), ',') }}%) | − {{ $money($booking->fee_amount) }} |
| Importo dovuto | **{{ $money($booking->amount_due) }}** |
@endif
| Acconto ({{ rtrim(rtrim(number_format((float) $booking->deposit_percent, 2, ',', ''), '0'), ',') }}%) | {{ $money($booking->deposit_amount) }} |
</x-mail::table>

@if ($isOperator)
<x-mail::panel>
**Referente:** {{ $booking->contactFullName() }}<br>
**Telefono:** {{ $booking->contact_phone }}<br>
**Email:** {{ $booking->contact_email }}
@if ($booking->agency)
<br>**Agenzia:** {{ $booking->agency->company_name }} (P.IVA {{ $booking->agency->vat_number }}) · {{ $booking->agency->phone }} · {{ $booking->agency->email }}
@if ($booking->agency_reference)<br>**Rif. pratica agenzia:** {{ $booking->agency_reference }}@endif
@endif
@if ($booking->notes)
<br>**Note:** {{ $booking->notes }}
@endif
</x-mail::panel>
@endif

<x-mail::button :url="$button['url']">
{{ $button['label'] }}
</x-mail::button>

@unless ($isOperator)
Per qualsiasi domanda rispondi a questa email.
@endunless

Grazie,<br>
{{ $company }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $company }}@if (\App\Models\Setting::get('vat_number')) · P.IVA {{ \App\Models\Setting::get('vat_number') }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
