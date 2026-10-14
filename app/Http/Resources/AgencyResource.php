<?php

namespace App\Http\Resources;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agency */
class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'address' => $this->address,
            'city' => $this->city,
            'zip_code' => $this->zip_code,
            'province' => $this->province,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'pec' => $this->pec,
            'sdi_code' => $this->sdi_code,
            'contact_person' => $this->contact_person,
            'license_number' => $this->license_number,
        ];
    }
}
