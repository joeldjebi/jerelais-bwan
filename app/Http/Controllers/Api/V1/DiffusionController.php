<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\ShowcaseController;
use App\Http\Resources\V1\DiffusionResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Fil « Diffusé sur REFLET TV » (MOB-25) : les relais publiés par la rédaction sur la vitrine (WEB-05),
 * avec le texte rédigé par la rédaction et le crédit choisi par le Relais. Aucune donnée personnelle.
 */
#[Group('Diffusions', 'Fil public des relais diffusés sur REFLET TV (MOB-25).', weight: 11)]
class DiffusionController extends Controller
{
    /**
     * Fil « Diffusé sur REFLET TV ».
     *
     * Relais passés à l'antenne et publiés par la rédaction, du plus récent au plus ancien, 20 par page.
     * Titre et texte rédigés par la rédaction, crédit choisi par le Relais (nom, pseudonyme ou anonyme), vignette sans métadonnées.
     * Public, mis en cache 5 minutes. Aucune donnée personnelle.
     * Filtres facultatifs, comme sur le site : `q` (recherche), `commune` et `categorie` (slug, voir `GET /communes`
     * et `GET /categories`), `periode` (`semaine`, `mois`, `annee`), `tri` (`recents` par défaut, `anciens`).
     */
    #[QueryParameter('q', 'Recherche dans le titre, le texte ou l’émission.', type: 'string', example: 'inondation')]
    #[QueryParameter('commune', 'Slug de la commune.', type: 'string', example: 'abobo')]
    #[QueryParameter('categorie', 'Slug de la catégorie.', type: 'string', example: 'faits-divers-et-accidents')]
    #[QueryParameter('periode', 'Diffusés depuis : `semaine`, `mois` ou `annee`.', type: 'string', example: 'mois')]
    #[QueryParameter('tri', '`recents` (par défaut) ou `anciens`.', type: 'string', example: 'recents')]
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return DiffusionResource::collection(ShowcaseController::query(ShowcaseController::filters($request))->paginate(20)->withQueryString());
    }
}
