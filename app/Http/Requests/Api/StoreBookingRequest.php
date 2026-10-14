<?php

namespace App\Http\Requests\Api;

use App\Enums\DocumentType;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],

            'contact' => ['required', 'array'],
            'contact.first_name' => ['required', 'string', 'max:100'],
            'contact.last_name' => ['required', 'string', 'max:100'],
            'contact.email' => ['required', 'email', 'max:255'],
            'contact.phone' => ['required', 'string', 'max:30'],

            // Il primo partecipante è il capogruppo.
            'participants' => ['required', 'array', 'min:1', 'max:'.QuoteRequest::MAX_PARTICIPANTS],
            'participants.*.first_name' => ['required', 'string', 'max:100'],
            'participants.*.last_name' => ['required', 'string', 'max:100'],
            'participants.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'participants.*.gender' => ['required', Rule::enum(Gender::class)],
            'participants.*.tax_code' => ['nullable', 'string', 'max:20'],
            'participants.*.nationality' => ['nullable', 'string', 'max:50'],
            'participants.*.document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'participants.*.document_number' => ['nullable', 'string', 'max:50'],
            'participants.*.document_expiry' => ['nullable', 'date_format:Y-m-d'],

            'excursion_ids' => ['nullable', 'array'],
            'excursion_ids.*' => ['integer', 'distinct'],
            'insurance_ids' => ['nullable', 'array'],
            'insurance_ids.*' => ['integer', 'distinct'],

            'notes' => ['nullable', 'string', 'max:2000'],
            'agency_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'package_id' => 'pacchetto',
            'departure_date' => 'data di partenza',
            'contact.first_name' => 'nome referente',
            'contact.last_name' => 'cognome referente',
            'contact.email' => 'email referente',
            'contact.phone' => 'telefono referente',
            'participants' => 'partecipanti',
            'participants.*.first_name' => 'nome',
            'participants.*.last_name' => 'cognome',
            'participants.*.birth_date' => 'data di nascita',
            'participants.*.gender' => 'sesso',
            'participants.*.tax_code' => 'codice fiscale',
            'participants.*.nationality' => 'nazionalità',
            'participants.*.document_type' => 'tipo documento',
            'participants.*.document_number' => 'numero documento',
            'participants.*.document_expiry' => 'scadenza documento',
            'agency_reference' => 'riferimento pratica',
        ];
    }
}
