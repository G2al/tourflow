<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * Tutti vedono il prezzo al pubblico. Le agenzie ricevono in più il dettaglio
 * della loro fee ("agencyFee"), da mostrare su richiesta.
 */
trait ExposesPrices
{
    protected function agencyFeePercent(Request $request): ?float
    {
        $user = $request->user('sanctum');

        return $user?->isAgency() ? $user->agency?->feePercent() : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function agencyFee(Request $request, ?float $price): array
    {
        $percent = $this->agencyFeePercent($request);

        if ($percent === null) {
            return [];
        }

        $amount = $price === null ? null : round($price * $percent / 100, 2);

        return ['agencyFee' => [
            'percent' => $percent,
            'amount' => $amount,
            'netPrice' => $price === null ? null : round($price - $amount, 2),
        ]];
    }
}
