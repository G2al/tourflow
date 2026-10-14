<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * Gli ospiti vedono solo il prezzo lordo. Le agenzie vedono il netto in
 * "{key}" e il lordo (prezzo al pubblico) in "{key}Gross".
 */
trait ExposesPrices
{
    protected function isAgencyViewer(Request $request): bool
    {
        return (bool) $request->user('sanctum')?->isAgency();
    }

    protected function priceType(Request $request): string
    {
        return $this->isAgencyViewer($request) ? 'net' : 'gross';
    }

    /**
     * @return array<string, float|null>
     */
    protected function price(Request $request, string $key, mixed $gross, mixed $net): array
    {
        $gross = $gross === null ? null : (float) $gross;
        $net = $net === null ? null : (float) $net;

        if (! $this->isAgencyViewer($request)) {
            return [$key => $gross];
        }

        return [$key => $net, "{$key}Gross" => $gross];
    }
}
