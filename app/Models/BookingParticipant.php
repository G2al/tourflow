<?php

namespace App\Models;

use App\Enums\AgeCategory;
use App\Enums\DocumentType;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingParticipant extends Model
{
    protected $fillable = [
        'booking_id',
        'is_lead',
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'tax_code',
        'nationality',
        'document_type',
        'document_number',
        'document_expiry',
        'age_category',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'birth_date' => 'date',
            'document_expiry' => 'date',
            'gender' => Gender::class,
            'document_type' => DocumentType::class,
            'age_category' => AgeCategory::class,
            'price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
