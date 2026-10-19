<?php

namespace Database\Seeders;

use App\Domain\Legal\Actions\PublishLegalDocument;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Legal\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Versions provisoires des textes (§9), publiées seulement si aucune version n'existe.
 * Les textes définitifs sont rédigés ou validés par le conseil juridique de REFLET TV, puis publiés depuis la console.
 */
class LegalDocumentsSeeder extends Seeder
{
    public function run(PublishLegalDocument $publish): void
    {
        foreach ($this->texts() as $type => [$title, $body]) {
            $legalType = LegalDocumentType::from($type);

            if (LegalDocument::current($legalType) === null) {
                $publish->handle($legalType, $title, '> '.__('legal.provisional')."\n\n".$body);
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function texts(): array
    {
        return [
            'charte' => ['Charte du Relais', <<<'MD'
                En devenant Relais, je m'engage à :

                1. **Transmettre uniquement ce que j'ai vu ou entendu moi-même**, sans mise en scène ni retouche trompeuse.
                2. **Ne jamais me mettre en danger** ni gêner les forces de l'ordre ou les secours.
                3. **Respecter la dignité des personnes**, en particulier des victimes et des personnes vulnérables.
                4. **Ne pas filmer de mineurs identifiables sans autorisation**, et le signaler lors de l'envoi.
                5. **Ne pas envoyer de contenus haineux, diffamatoires, violents gratuitement ou contraires à la loi.**
                6. **Ne pas me présenter comme journaliste ou salarié de REFLET TV.**
                7. **Accepter que la rédaction reste seule juge de la diffusion.**

                L'envoi volontaire de fausses informations ou de contenus illicites entraîne la suspension, puis la radiation du réseau.
                MD],
            'conditions' => ['Conditions d’utilisation', <<<'MD'
                ## Objet

                JE RELAIS est la plateforme des observateurs citoyens de REFLET TV. Elle permet aux Relais d'envoyer des photos, vidéos et sons, vérifiés par un coordonnateur communal avant toute diffusion.

                ## Droits sur les contenus

                À chaque envoi, le Relais confirme être l'auteur du contenu et concède à REFLET TV le droit de l'exploiter (diffusion TV, web, réseaux sociaux, archives). La nature exacte de la cession (exclusive ou non, durée, territoire, rémunération éventuelle) sera précisée dans la version définitive.

                Le Relais choisit d'être crédité (nom ou pseudonyme) ou de rester anonyme ; ce choix est transmis à la rédaction avec chaque média.

                ## Compte

                Le compte est personnel. Le Relais protège l'accès à son téléphone et signale toute perte. REFLET TV peut suspendre un compte qui ne respecte pas la charte.
                MD],
            'confidentialite' => ['Politique de confidentialité', <<<'MD'
                ## Données collectées

                Identité, photo, pièce d'identité, selfie de contrôle, numéro de téléphone, commune et quartier, et pour chaque envoi la position GPS et la date de prise de vue. Seules les données utiles au fonctionnement du réseau sont collectées.

                ## Qui y a accès

                Les pièces d'identité ne sont visibles que par le coordonnateur de votre commune et les administrateurs habilités ; chaque consultation est enregistrée. Votre numéro de téléphone et votre position précise ne sont jamais publiés.

                ## Vos droits

                Vous pouvez accéder à vos données, les rectifier et supprimer votre compte depuis l'application. Le traitement est déclaré auprès de l'ARTCI, autorité de protection des données en Côte d'Ivoire.

                ## Conservation

                Les durées de conservation (candidatures refusées, comptes inactifs, relais rejetés) sont fixées par REFLET TV et appliquées automatiquement.
                MD],
        ];
    }
}
