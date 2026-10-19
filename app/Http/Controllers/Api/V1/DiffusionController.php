<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\ShowcaseController;
use App\Http\Resources\V1\DiffusionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fil « Diffusé sur REFLET TV » (MOB-25) : les relais publiés par la rédaction sur la vitrine (WEB-05),
 * avec le texte rédigé par la rédaction et le crédit choisi par le Relais. Aucune donnée personnelle.
 */
class DiffusionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return DiffusionResource::collection(ShowcaseController::query()->paginate(20));
    }
}
