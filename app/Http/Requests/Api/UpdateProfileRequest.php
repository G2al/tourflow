<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'current_password' => ['required_with:password', 'current_password:sanctum'],
            'password' => ['sometimes', 'confirmed', Password::min(8)],

            'agency' => ['sometimes', 'array'],
            'agency.company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'agency.vat_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('agencies', 'vat_number')->ignore($user->agency?->id)],
            'agency.tax_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'agency.address' => ['sometimes', 'required', 'string', 'max:255'],
            'agency.city' => ['sometimes', 'required', 'string', 'max:255'],
            'agency.zip_code' => ['sometimes', 'required', 'string', 'max:10'],
            'agency.province' => ['sometimes', 'required', 'string', 'max:5'],
            'agency.country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'agency.phone' => ['sometimes', 'required', 'string', 'max:30'],
            'agency.email' => ['sometimes', 'required', 'email', 'max:255'],
            'agency.pec' => ['sometimes', 'nullable', 'email', 'max:255'],
            'agency.sdi_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'agency.contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'agency.license_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return (new RegisterAgencyRequest)->attributes();
    }
}
