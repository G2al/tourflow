<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegalPageResource;
use App\Models\LegalPage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalPageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LegalPageResource::collection(LegalPage::active()->get());
    }

    public function show(LegalPage $legalPage): LegalPageResource
    {
        abort_unless($legalPage->is_active, 404);

        return new LegalPageResource($legalPage);
    }
}
