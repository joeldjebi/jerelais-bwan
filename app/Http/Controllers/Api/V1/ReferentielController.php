<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contribution\Models\Category;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\CommuneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Référentiels nécessaires à l'application, y compris avant connexion (candidature).
 */
class ReferentielController extends Controller
{
    /**
     * Communes ouvertes aux Relais.
     *
     * @unauthenticated
     */
    public function communes(): AnonymousResourceCollection
    {
        return CommuneResource::collection(
            Commune::query()->active()->with('region')->orderBy('name')->get()
        );
    }

    /**
     * Catégories de relais.
     *
     * @unauthenticated
     */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->active()->get());
    }

    /**
     * Paramètres utiles à l'application (durées et tailles maximales des médias, âge minimum).
     *
     * @unauthenticated
     */
    public function settings(Settings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->public()]);
    }
}
