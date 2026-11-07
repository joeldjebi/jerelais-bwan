<?php

namespace Database\Seeders;

use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Models\QuizQuestion;
use Illuminate\Database\Seeder;

/**
 * Contenu provisoire de la formation et du quiz (MOB-05), à valider par la rédaction de REFLET TV. Idempotent.
 */
class FormationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->modules() as $position => [$slug, $icon, $title, $summary, $body]) {
            FormationModule::query()->updateOrCreate(['slug' => $slug], [
                'icon' => $icon,
                'title' => $title,
                'summary' => $summary,
                'body' => $body,
                'position' => $position,
                'is_active' => true,
            ]);
        }

        if (QuizQuestion::query()->exists()) {
            return;
        }

        foreach ($this->questions() as $position => [$prompt, $choices, $correct, $explanation]) {
            $question = QuizQuestion::query()->create(['prompt' => $prompt, 'explanation' => $explanation, 'position' => $position]);

            foreach ($choices as $index => $label) {
                $question->choices()->create(['label' => $label, 'is_correct' => $index === $correct, 'position' => $index]);
            }
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function modules(): array
    {
        return [
            ['bien-filmer', 'video', 'Bien filmer', 'Une image utile pour la rédaction, en quelques réflexes.', <<<'MD'
                - **Tenez le téléphone à l'horizontale** : l'image remplit l'écran de télévision.
                - **Restez stable** : appuyez-vous contre un mur, filmez au moins **10 secondes** par plan, sans zoomer.
                - **Montrez le contexte** : un plan large du lieu, puis des plans plus serrés.
                - **Gardez le son** : il raconte souvent autant que l'image. Évitez de commenter pendant la prise.
                - **Envoyez l'original** : pas de filtre, pas de montage, pas de musique ajoutée.
                MD],
            ['securite', 'shield', 'Votre sécurité d’abord', 'Aucune image ne vaut un risque pour vous ou pour les autres.', <<<'MD'
                - **Ne vous mettez jamais en danger** : incendie, violence, inondation, foule… filmez de loin ou pas du tout.
                - **Laissez travailler les secours et les forces de l'ordre** : ne gênez jamais une intervention.
                - **Restez discret** si la situation est tendue. Vous pouvez toujours envoyer un témoignage audio plus tard.
                - **Ne vous présentez pas comme journaliste** : vous êtes un observateur citoyen.
                MD],
            ['droit-image', 'users', 'Droit à l’image et dignité', 'Respecter les personnes que l’on filme.', <<<'MD'
                - **Les mineurs identifiables** ne peuvent pas être diffusés sans autorisation de leurs parents : signalez toujours leur présence à l'envoi.
                - **Les victimes** et les personnes vulnérables doivent être respectées : pas de gros plan sur des blessés ou des corps.
                - **Demandez l'accord** des personnes que vous filmez de près quand c'est possible, et indiquez-le.
                - La rédaction floute si nécessaire, mais elle a besoin de vos indications.
                MD],
            ['fausses-informations', 'search', 'Fausses informations', 'Transmettre ce que l’on a vu, pas ce que l’on a entendu dire.', <<<'MD'
                - **N'envoyez que ce que vous avez vu ou entendu vous-même**, au moment où vous l'avez capté.
                - **Ne transférez pas** une vidéo reçue sur WhatsApp ou vue sur les réseaux : elle peut être ancienne ou truquée.
                - **Décrivez les faits**, sans les interpréter : où, quand, ce que vous voyez.
                - Envoyer volontairement une fausse information entraîne la suspension, puis la radiation du réseau.
                MD],
        ];
    }

    /**
     * @return list<array{0: string, 1: list<string>, 2: int, 3: string}>
     */
    private function questions(): array
    {
        return [
            ['Comment tenir votre téléphone pour filmer ?', ['À la verticale, comme pour un appel', 'À l’horizontale', 'Peu importe'], 1,
                'À l’horizontale, l’image remplit l’écran de télévision.'],
            ['Un incendie se déclare dans votre rue. Que faites-vous ?', ['Je m’approche au plus près pour filmer', 'Je filme de loin, en sécurité, sans gêner les secours', 'Je demande aux pompiers de m’attendre'], 1,
                'Votre sécurité passe avant tout, et les secours ne doivent jamais être gênés.'],
            ['Vous recevez une vidéo choc sur WhatsApp. Pouvez-vous l’envoyer comme relais ?', ['Oui, si elle semble récente', 'Oui, en précisant qu’elle vient de WhatsApp', 'Non, je n’envoie que ce que j’ai capté moi-même'], 2,
                'Un relais est un témoignage direct. Une vidéo transférée peut être ancienne ou truquée.'],
            ['Des élèves identifiables apparaissent dans votre vidéo. Que faites-vous ?', ['Je le signale lors de l’envoi', 'Je n’en parle pas, la rédaction verra', 'Je ne peux rien envoyer'], 0,
                'Signalez toujours la présence de mineurs : la rédaction floute ou demande une autorisation.'],
            ['Pouvez-vous vous présenter comme journaliste de REFLET TV ?', ['Oui, avec mon badge', 'Non, je suis un observateur citoyen', 'Seulement en cas d’urgence'], 1,
                'Le badge de Relais n’est pas une carte de presse : la charte l’interdit.'],
            ['Combien de temps filmer un plan au minimum ?', ['2 secondes', 'Environ 10 secondes, sans zoomer', 'Une minute en zoomant'], 1,
                'Des plans stables d’une dizaine de secondes sont utilisables au montage.'],
            ['Pouvez-vous ajouter un filtre ou de la musique avant l’envoi ?', ['Oui, pour rendre la vidéo plus belle', 'Non, j’envoie l’original', 'Seulement de la musique'], 1,
                'La rédaction a besoin du fichier original, sans retouche.'],
            ['Qui décide de la diffusion d’un relais ?', ['Le Relais', 'Le coordonnateur, puis la rédaction de REFLET TV', 'La première personne qui le voit'], 1,
                'Le coordonnateur vérifie, et la rédaction reste seule juge de la diffusion.'],
        ];
    }
}
