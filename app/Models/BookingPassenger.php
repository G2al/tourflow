<?php

namespace App\Models;

use App\Enums\AgeCategory;
use App\Enums\DocumentType;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPassenger extends Model
{
    protected $fillable = [
        'booking_id',
        'position',
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
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: "Passeggero {$this->position}";
    }
}
