<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\BookingItem;
use App\Models\BookingItemRoom;
use App\Models\BookingPassenger;
use App\Support\CamelKeys;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Pratica completa. Contiene l'access token: va restituita solo a chi può
 * già accedere alla pratica (agenzia proprietaria o titolare del link).
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $optionActive = $status === BookingStatus::Option && ! $this->isOptionExpired();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'accessToken' => $this->access_token,
            'status' => $status->value,
            'statusLabel' => $status->getLabel(),
            'isAgencyBooking' => $this->isAgencyBooking(),
            'contact' => [
                'firstName' => $this->contact_first_name,
                'lastName' => $this->contact_last_name,
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
            ],
            'agencyReference' => $this->agency_reference,
            'notes' => $this->notes,
            'passengers' => $this->passengers->map(fn (BookingPassenger $p) => [
                'position' => $p->position,
                'isLead' => $p->is_lead,
                'firstName' => $p->first_name,
                'lastName' => $p->last_name,
                'birthDate' => $p->birth_date->toDateString(),
                'gender' => $p->gender?->value,
                'taxCode' => $p->tax_code,
                'nationality' => $p->nationality,
                'documentType' => $p->document_type?->value,
                'documentNumber' => $p->document_number,
                'documentExpiry' => $p->document_expiry?->toDateString(),
                'ageCategory' => $p->age_category->value,
            ]),
            'items' => $this->items->map(fn (BookingItem $item) => [
                'id' => $item->id,
                'packageId' => $item->package_id,
                'packageSlug' => $item->package?->slug,
                'title' => $item->title,
                'mode' => $item->mode->value,
                'modeLabel' => $item->mode->getLabel(),
                'startDate' => $item->start_date->toDateString(),
                'endDate' => $item->end_date->toDateString(),
                'nights' => $item->nights,
                'hotel' => $item->hotel ? ['id' => $item->hotel->id, 'name' => $item->hotel->name] : null,
                'flight' => $item->flight ? new FlightResource($item->flight) : null,
                'seats' => $item->seats,
                'passengers' => $item->passenger_positions,
                'rooms' => $item->rooms->map(fn (BookingItemRoom $room) => [
                    'roomTypeId' => $room->room_type_id,
                    'roomType' => $room->roomType?->name,
                    'checkIn' => $room->check_in->toDateString(),
                    'checkOut' => $room->check_out->toDateString(),
                    'passengers' => $room->passenger_positions,
                    'total' => (float) $room->total,
                ]),
                'breakdown' => CamelKeys::convert($item->breakdown),
                'total' => (float) $item->total,
            ]),
            'insurances' => $this->insurances->map(fn (BookingInsurance $insurance) => [
                'insuranceId' => $insurance->insurance_id,
                'name' => $insurance->name,
                'unitPrice' => (float) $insurance->unit_price,
                'quantity' => $insurance->quantity,
                'total' => (float) $insurance->total,
            ]),
            'priceLock' => ['selected' => $this->price_lock, 'amount' => (float) $this->price_lock_amount],
            'totals' => [
                'items' => (float) $this->items_total,
                'insurances' => (float) $this->insurances_total,
                'priceLock' => (float) $this->price_lock_amount,
                'total' => (float) $this->total,
                'feePercent' => $this->fee_percent === null ? null : (float) $this->fee_percent,
                'feeAmount' => (float) $this->fee_amount,
                'amountDue' => (float) $this->amount_due,
                'depositPercent' => (float) $this->deposit_percent,
                'depositAmount' => (float) $this->deposit_amount,
            ],
            'currency' => 'EUR',
            'quoteValidUntil' => $this->quote_valid_until?->toDateString(),
            'optionExpiresAt' => $this->option_expires_at?->toIso8601String(),
            'confirmedAt' => $this->confirmed_at?->toIso8601String(),
            'proformaSentAt' => $this->proforma_sent_at?->toIso8601String(),
            'proformaViewedAt' => $this->proforma_viewed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'termsAcceptedAt' => $this->terms_accepted_at?->toIso8601String(),
            'actions' => [
                'canOption' => $status === BookingStatus::Quote,
                'canConfirm' => $status === BookingStatus::Quote || $optionActive,
                'canCancel' => $status === BookingStatus::Quote || $status === BookingStatus::Option,
            ],
            'documents' => [
                'quotePdfUrl' => route('api.bookings.quote-pdf', ['booking' => $this->code, 'token' => $this->access_token]),
                'proformaUrl' => $this->isAgencyBooking() && $this->proforma_sent_at && ! $status->isFinal()
                    ? URL::signedRoute('api.bookings.proforma', ['booking' => $this->code])
                    : null,
            ],
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
