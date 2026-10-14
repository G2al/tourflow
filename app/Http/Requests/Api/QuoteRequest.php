<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public const MAX_PARTICIPANTS = 10;

    public function rules(): array
    {
        return [
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'participants' => ['required', 'array', 'min:1', 'max:'.self::MAX_PARTICIPANTS],
            'participants.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'excursion_ids' => ['nullable', 'array'],
            'excursion_ids.*' => ['integer', 'distinct'],
            'insurance_ids' => ['nullable', 'array'],
            'insurance_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function attributes(): array
    {
        return [
            'departure_date' => 'data di partenza',
            'participants' => 'partecipanti',
            'participants.*.birth_date' => 'data di nascita',
        ];
    }
}
