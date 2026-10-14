<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Services\WhatsAppLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'departure_date' => $this->departure_date->toDateString(),
            'return_date' => $this->return_date->toDateString(),
            'package' => [
                'id' => $this->package->id,
                'title' => $this->package->title,
                'slug' => $this->package->slug,
                'nights' => $this->package->nights,
                'destination' => $this->package->destination?->name,
                'hotel' => $this->package->hotel?->name,
            ],
            'contact' => [
                'first_name' => $this->contact_first_name,
                'last_name' => $this->contact_last_name,
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
            ],
            'agency_reference' => $this->agency_reference,
            'notes' => $this->notes,
            'participants' => $this->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'is_lead' => $participant->is_lead,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'birth_date' => $participant->birth_date->toDateString(),
                'gender' => $participant->gender->value,
                'tax_code' => $participant->tax_code,
                'nationality' => $participant->nationality,
                'document_type' => $participant->document_type?->value,
                'document_number' => $participant->document_number,
                'document_expiry' => $participant->document_expiry?->toDateString(),
                'age_category' => $participant->age_category->value,
                'price' => (float) $participant->price,
            ]),
            'extras' => $this->extras->map(fn ($extra) => [
                'type' => $extra->type,
                'id' => $extra->extraable_id,
                'name' => $extra->name,
                'quantity' => $extra->quantity,
                'total' => (float) $extra->total_price,
            ]),
            'price_type' => $this->price_type,
            'currency' => 'EUR',
            'total' => (float) $this->total_amount,
            'total_gross' => $this->when($this->isNetPrice(), fn () => (float) $this->total_gross),
            'price_breakdown' => $this->price_breakdown,
            'whatsapp_url' => WhatsAppLink::forBooking($this->resource),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
