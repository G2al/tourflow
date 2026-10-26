<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Converte ricorsivamente le chiavi di un array in camelCase (formato delle
 * risposte API di catalogo, carrello e pratiche).
 */
class CamelKeys
{
    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function convert(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[is_string($key) ? Str::camel($key) : $key] = is_array($value) ? self::convert($value) : $value;
        }

        return $result;
    }
}
