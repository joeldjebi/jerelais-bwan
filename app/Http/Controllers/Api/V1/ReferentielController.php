<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contribution\Models\Category;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\CommuneResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Référentiels nécessaires à l'application, y compris avant connexion (candidature).
 */
#[Group('Référentiels', 'Listes publiques nécessaires avant et après connexion, mises en cache 5 minutes.', weight: 3)]
class ReferentielController extends Controller
{
    /**
     * Communes ouvertes aux Relais.
     *
     * Communes ouvertes aux inscriptions, avec leur région, triées par nom. Sert au formulaire de candidature
     * et au champ `declared_commune` d'un relais. Mise en cache 5 minutes.
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
     * Catégories actives, dans l'ordre d'affichage, avec des exemples de sujets. L'`uuid` sert au champ `category` d'un relais.
     *
     * @unauthenticated
     */
    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->active()->get());
    }

    /**
     * Paramètres utiles à l'application.
     *
     * Valeurs fixées par l'administration, à appliquer avant l'envoi pour éviter un refus :
     * durée maximale d'une vidéo et d'un audio (secondes), nombre de médias par relais, taille maximale d'un fichier (Mo),
     * âge minimum et admission des mineurs (candidature), seuils des niveaux Argent et Or (relais validés).
     *
     * @response array{data: array{'media.video_max_seconds': int, 'media.audio_max_seconds': int, 'media.max_medias_per_relais': int, 'media.max_file_size_mb': int, 'candidature.minimum_age': int, 'candidature.minors_allowed': bool, 'levels.silver_validated': int, 'levels.gold_validated': int}}
     *
     * @unauthenticated
     */
    public function settings(Settings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->public()]);
    }
}
