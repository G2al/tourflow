<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SettingController extends Controller
{
    /**
     * Dati pubblici del tour operator e regole di prenotazione (fasce d'età,
     * durata delle opzioni, blocca prezzo, acconto...).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Arr::only(Setting::values(), Setting::PUBLIC_KEYS)]);
    }
}
