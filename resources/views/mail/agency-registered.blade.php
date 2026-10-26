@php
    $company = \App\Models\Setting::get('company_name');
    $fee = rtrim(rtrim(number_format($agency->feePercent(), 2, ',', ''), '0'), ',');
@endphp
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('tourflow.frontend_url')">
{{ $company }}
</x-mail::header>
</x-slot:header>

@if ($isOperator)
# Nuova agenzia registrata

Un'agenzia si è registrata ed è già operativa con la fee predefinita del {{ $fee }}%.

<x-mail::panel>
**{{ $agency->company_name }}** · P.IVA {{ $agency->vat_number }}<br>
{{ $agency->fullAddress() }}<br>
{{ $agency->email }} · {{ $agency->phone }}<br>
Operatore: {{ $agency->user->name }} ({{ $agency->user->email }})
</x-mail::panel>

Dal pannello puoi modificare la fee o disattivare l'account.

<x-mail::button :url="$panelUrl">
Apri nel pannello
</x-mail::button>
@else
# Benvenuto, {{ $agency->company_name }}!

La registrazione all'area agenzie di {{ $company }} è completata: puoi già accedere al sito, vedere i prezzi con il dettaglio della tua fee e creare preventivi, opzioni e prenotazioni.

<x-mail::panel>
**La tua fee:** {{ $fee }}% sul totale di ogni pratica.<br>
**Opzione:** puoi bloccare la disponibilità per {{ \App\Models\Setting::get('option_hours_agency') }} ore.
</x-mail::panel>

<x-mail::button :url="config('tourflow.frontend_url')">
Vai al sito
</x-mail::button>
@endif

Grazie,<br>
{{ $company }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $company }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
