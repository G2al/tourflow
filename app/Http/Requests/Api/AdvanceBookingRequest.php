<?php

namespace App\Http\Requests\Api;

use App\Enums\DocumentType;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Passaggio di una pratica a opzione o conferma: accettazione delle condizioni
 * ed eventuale completamento dei dati dei passeggeri.
 */
class AdvanceBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'terms_accepted' => ['required', 'accepted'],
            'passengers' => ['nullable', 'array'],
            'passengers.*.birth_date' => ['required_with:passengers', 'date_format:Y-m-d'],
            'passengers.*.first_name' => ['nullable', 'string', 'max:100'],
            'passengers.*.last_name' => ['nullable', 'string', 'max:100'],
            'passengers.*.gender' => ['nullable', Rule::enum(Gender::class)],
            'passengers.*.tax_code' => ['nullable', 'string', 'max:20'],
            'passengers.*.nationality' => ['nullable', 'string', 'max:50'],
            'passengers.*.document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'passengers.*.document_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.document_expiry' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'terms_accepted' => 'accettazione delle condizioni',
            'passengers.*.birth_date' => 'data di nascita',
        ];
    }
}
