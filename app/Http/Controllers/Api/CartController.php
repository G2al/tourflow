<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CartRequest;
use App\Services\BookingService;
use App\Support\CamelKeys;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    /**
     * Prezzo e disponibilità del carrello, senza salvare nulla. Da chiamare a
     * ogni modifica del carrello.
     */
    public function quote(CartRequest $request, BookingService $bookings): JsonResponse
    {
        return response()->json([
            'data' => CamelKeys::convert($bookings->quote($request->validated(), $request->user('sanctum'))),
        ]);
    }
}
