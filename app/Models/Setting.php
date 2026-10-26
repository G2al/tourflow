<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const DEFAULTS = [
        // Tour operator
        'company_name' => 'TourFlow',
        'whatsapp_number' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'address' => '',
        'vat_number' => '',

        // Fasce d'età (età compiuta alla data del servizio)
        'infant_max_age' => '1',
        'child_max_age' => '17',

        // Prenotazioni
        'default_agency_fee_percent' => '10',
        'option_hours_agency' => '72',
        'option_hours_guest' => '6',
        'price_lock_amount' => '49',
        'deposit_percent' => '30',
        'quote_validity_days' => '7',

        // Bonifico (proforma)
        'bank_beneficiary' => '',
        'bank_name' => '',
        'bank_iban' => '',
    ];

    /** Chiavi esposte pubblicamente dalle API. */
    public const PUBLIC_KEYS = [
        'company_name', 'whatsapp_number', 'contact_email', 'contact_phone', 'address', 'vat_number',
        'infant_max_age', 'child_max_age', 'option_hours_agency', 'option_hours_guest',
        'price_lock_amount', 'deposit_percent', 'quote_validity_days',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::values()[$key] ?? $default;
    }

    public static function int(string $key): int
    {
        return (int) static::get($key, self::DEFAULTS[$key] ?? 0);
    }

    public static function float(string $key): float
    {
        return (float) static::get($key, self::DEFAULTS[$key] ?? 0);
    }

    /**
     * Tutte le impostazioni, con i valori di default per le chiavi mancanti.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        // In cache solo i valori salvati: i default si uniscono a ogni lettura,
        // così le nuove impostazioni sono subito disponibili dopo un deploy.
        return array_merge(
            self::DEFAULTS,
            Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all()),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('settings');
    }
}
