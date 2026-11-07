<?php

namespace Database\Seeders;

use App\Domain\Contribution\Models\Category;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Listes de départ, modifiables ensuite par l'administrateur :
 * catégories (Annexe B), motifs de rejet des relais (COO-15) et des candidatures (COO-02).
 */
class ListsSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string}> */
    private const CATEGORIES = [
        ['Faits divers et accidents', 'Accident de la circulation, incendie, effondrement'],
        ['Météo et environnement', 'Pluies, inondations, érosion, insalubrité, pollution'],
        ['Vie de quartier', 'Travaux, coupures d’eau ou d’électricité, voirie, éclairage'],
        ['Éducation', 'Vie des écoles et universités, examens, rentrée, infrastructures'],
        ['Santé', 'Centres de santé, campagnes, situations sanitaires'],
        ['Économie et consommation', 'Marchés, prix, commerces, transport'],
        ['Société et solidarité', 'Initiatives citoyennes, associations, portraits'],
        ['Culture, sport et loisirs', 'Événements, concerts, compétitions locales'],
        ['Politique et institutions', 'Vie municipale, cérémonies officielles, services publics'],
        ['Autre', 'Tout sujet ne relevant pas des catégories ci-dessus'],
    ];

    /** @var list<string> */
    private const RELAIS_REASONS = [
        'Hors sujet', 'Qualité insuffisante', 'Information non vérifiable', 'Contenu ancien',
        'Contenu déjà publié ailleurs', 'Atteinte à la dignité', 'Mineurs identifiables',
        'Contenu dangereux ou illicite', 'Doublon',
    ];

    /** @var list<string> */
    private const CANDIDATURE_REASONS = [
        'Dossier incomplet', 'Pièce d’identité illisible ou non valide', 'Identité non vérifiable',
        'Âge non conforme', 'Candidature en double', 'Hors de la commune', 'Autre motif',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $position => [$name, $examples]) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'examples' => $examples, 'position' => $position],
            );
        }

        foreach ([RejectionTarget::Relais->value => self::RELAIS_REASONS, RejectionTarget::Candidature->value => self::CANDIDATURE_REASONS] as $target => $labels) {
            foreach ($labels as $position => $label) {
                RejectionReason::query()->updateOrCreate(
                    ['applies_to' => $target, 'slug' => Str::slug($label)],
                    ['label' => $label, 'position' => $position],
                );
            }
        }
    }
}
