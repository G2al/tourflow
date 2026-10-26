<?php

namespace App\Http\Requests\Api;

use App\Enums\BookingAction;
use Illuminate\Validation\Rule;

/**
 * Creazione della pratica dal carrello con la scelta finale:
 * preventivo, opzione o conferma.
 */
class StoreBookingRequest extends CartRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'action' => ['required', Rule::enum(BookingAction::class)],
            'contact' => ['required', 'array'],
            'contact.first_name' => ['required', 'string', 'max:100'],
            'contact.last_name' => ['required', 'string', 'max:100'],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['required', 'string', 'max:30'],
            'terms_accepted' => [Rule::requiredIf(fn () => $this->input('action') !== BookingAction::Quote->value), 'accepted_if:action,option,confirm'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agency_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'action' => 'tipo di richiesta',
            'contact.first_name' => 'nome referente',
            'contact.last_name' => 'cognome referente',
            'contact.email' => 'email referente',
            'contact.phone' => 'telefono referente',
            'terms_accepted' => 'accettazione delle condizioni',
            'agency_reference' => 'riferimento pratica',
        ];
    }
}
