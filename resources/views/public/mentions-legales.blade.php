<x-layouts.public :title="__('Mentions légales')">
    <article class="bg-white px-5 pt-32 pb-28 sm:pt-40">
        <div class="mx-auto max-w-[720px]">
            <h1 class="text-[clamp(2.5rem,6vw,4rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-anim>{{ __('Mentions légales') }}</h1>
            <div class="legal-prose mt-10 text-[1.0625rem]" data-anim>
                <blockquote>{{ __('Texte provisoire, à compléter et valider par le conseil juridique de REFLET TV.') }}</blockquote>
                <h2>{{ __('Éditeur') }}</h2>
                <p>{{ __('JE RELAIS est édité par REFLET TV, chaîne de télévision ivoirienne diffusée sur le bouquet CANAL+. Raison sociale, siège, capital, immatriculation et directeur de la publication : [ À COMPLÉTER ].') }}</p>
                <h2>{{ __('Hébergement') }}</h2>
                <p>{{ __('Hébergeur de la plateforme et du stockage des médias : [ À COMPLÉTER ].') }}</p>
                <h2>{{ __('Données personnelles') }}</h2>
                <p>{{ __('Le traitement des données des Relais est déclaré auprès de l’ARTCI. Pour exercer vos droits, voir la politique de confidentialité ou écrire via la page Contact.') }}</p>
                <h2>{{ __('Contenus') }}</h2>
                <p>{{ __('Les contenus envoyés par les Relais sont vérifiés par un coordonnateur communal avant toute diffusion. La rédaction reste seule juge de la diffusion.') }}</p>
            </div>
        </div>
    </article>
</x-layouts.public>
