<?php

namespace App\Http\Requests\Api;

use App\Enums\BookingMode;
use App\Enums\DocumentType;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Carrello: passeggeri (la posizione nell'array è il loro identificativo),
 * prodotti con camere/passeggeri assegnati, assicurazioni e blocca prezzo.
 */
class CartRequest extends FormRequest
{
    public const MAX_PASSENGERS = 16;

    public const MAX_ITEMS = 10;

    public function rules(): array
    {
        return [
            'passengers' => ['required', 'array', 'min:1', 'max:'.self::MAX_PASSENGERS],
            'passengers.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'passengers.*.first_name' => ['nullable', 'string', 'max:100'],
            'passengers.*.last_name' => ['nullable', 'string', 'max:100'],
            'passengers.*.gender' => ['nullable', Rule::enum(Gender::class)],
            'passengers.*.tax_code' => ['nullable', 'string', 'max:20'],
            'passengers.*.nationality' => ['nullable', 'string', 'max:50'],
            'passengers.*.document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'passengers.*.document_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.document_expiry' => ['nullable', 'date_format:Y-m-d'],

            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.package_id' => ['required', 'integer'],
            'items.*.mode' => ['nullable', Rule::enum(BookingMode::class)],
            'items.*.check_in' => ['nullable', 'date_format:Y-m-d'],
            'items.*.check_out' => ['nullable', 'date_format:Y-m-d', 'after:items.*.check_in'],
            'items.*.flight_id' => ['nullable', 'integer'],
            'items.*.date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.rooms' => ['nullable', 'array', 'max:4'],
            'items.*.rooms.*.room_type_id' => ['required', 'integer'],
            'items.*.rooms.*.passengers' => ['required', 'array', 'min:1', 'max:4'],
            'items.*.rooms.*.passengers.*' => ['integer', 'min:0'],
            'items.*.passengers' => ['nullable', 'array', 'min:1'],
            'items.*.passengers.*' => ['integer', 'min:0'],

            'insurance_ids' => ['nullable', 'array'],
            'insurance_ids.*' => ['integer', 'distinct'],
            'price_lock' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'passengers' => 'passeggeri',
            'passengers.*.birth_date' => 'data di nascita',
            'passengers.*.first_name' => 'nome',
            'passengers.*.last_name' => 'cognome',
            'passengers.*.gender' => 'sesso',
            'passengers.*.tax_code' => 'codice fiscale',
            'passengers.*.nationality' => 'nazionalità',
            'passengers.*.document_type' => 'tipo documento',
            'passengers.*.document_number' => 'numero documento',
            'passengers.*.document_expiry' => 'scadenza documento',
            'items' => 'prodotti',
            'items.*.package_id' => 'pacchetto',
            'items.*.mode' => 'modalità',
            'items.*.check_in' => 'check-in',
            'items.*.check_out' => 'check-out',
            'items.*.flight_id' => 'volo',
            'items.*.date' => 'data',
            'items.*.rooms' => 'camere',
            'items.*.rooms.*.room_type_id' => 'tipologia camera',
            'items.*.rooms.*.passengers' => 'passeggeri della camera',
            'insurance_ids' => 'assicurazioni',
            'price_lock' => 'blocca prezzo',
        ];
    }
}
