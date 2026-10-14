<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterAgencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Operatore (utente che accede)
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['nullable', 'string', 'max:100'],

            // Dati agenzia
            'agency' => ['required', 'array'],
            'agency.company_name' => ['required', 'string', 'max:255'],
            'agency.vat_number' => ['required', 'string', 'max:20', 'unique:agencies,vat_number'],
            'agency.tax_code' => ['nullable', 'string', 'max:20'],
            'agency.address' => ['required', 'string', 'max:255'],
            'agency.city' => ['required', 'string', 'max:255'],
            'agency.zip_code' => ['required', 'string', 'max:10'],
            'agency.province' => ['nullable', 'string', 'max:5'],
            'agency.country' => ['nullable', 'string', 'size:2'],
            'agency.phone' => ['nullable', 'string', 'max:30'],
            'agency.email' => ['nullable', 'email', 'max:255'],
            'agency.pec' => ['nullable', 'email', 'max:255'],
            'agency.sdi_code' => ['nullable', 'string', 'max:10'],
            'agency.contact_person' => ['nullable', 'string', 'max:255'],
            'agency.license_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'agency.company_name' => 'ragione sociale',
            'agency.vat_number' => 'partita IVA',
            'agency.tax_code' => 'codice fiscale',
            'agency.address' => 'indirizzo',
            'agency.city' => 'città',
            'agency.zip_code' => 'CAP',
            'agency.province' => 'provincia',
            'agency.sdi_code' => 'codice SDI',
            'agency.contact_person' => 'referente',
            'agency.license_number' => 'numero licenza',
        ];
    }
}
