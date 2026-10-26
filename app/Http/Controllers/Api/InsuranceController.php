<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InsuranceResource;
use App\Models\Insurance;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsuranceController extends Controller
{
    /**
     * Assicurazioni facoltative aggiungibili a qualunque pratica (a persona).
     */
    public function __invoke(): AnonymousResourceCollection
    {
        return InsuranceResource::collection(Insurance::active()->orderBy('price')->get());
    }
}
