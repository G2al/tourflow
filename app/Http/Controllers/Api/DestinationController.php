<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DestinationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $destinations = Destination::active()
            ->withCount(['packages' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return DestinationResource::collection($destinations);
    }

    public function show(Destination $destination): DestinationResource
    {
        abort_unless($destination->is_active, 404);

        return new DestinationResource(
            $destination->loadCount(['packages' => fn ($query) => $query->published()])
        );
    }
}
