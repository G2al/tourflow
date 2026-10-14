<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'vat_number',
        'tax_code',
        'address',
        'city',
        'zip_code',
        'province',
        'country',
        'phone',
        'email',
        'pec',
        'sdi_code',
        'contact_person',
        'license_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function fullAddress(): string
    {
        return trim("{$this->address}, {$this->zip_code} {$this->city}".($this->province ? " ({$this->province})" : ''));
    }
}
