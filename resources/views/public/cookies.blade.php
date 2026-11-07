<x-layouts.public :title="__('Cookies')">
    <article class="bg-white px-5 pt-32 pb-28 sm:pt-40">
        <div class="mx-auto max-w-[720px]">
            <h1 class="text-[clamp(2.5rem,6vw,4rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-anim>{{ __('Cookies') }}</h1>
            <div class="legal-prose mt-10 text-[1.0625rem]" data-anim>
                <p>{{ __('JE RELAIS n’utilise ni cookie publicitaire, ni outil de mesure d’audience, ni traceur de réseaux sociaux. Aucun bandeau de consentement n’est donc nécessaire.') }}</p>
                <h2>{{ __('Cookies strictement nécessaires') }}</h2>
                <ul>
                    <li><strong>{{ __('Session') }}</strong> : {{ __('vous garde connecté·e à votre espace Relais ou à la console, le temps de votre visite.') }}</li>
                    <li><strong>{{ __('Protection CSRF') }}</strong> : {{ __('empêche qu’un autre site envoie des formulaires à votre place.') }}</li>
                </ul>
                <h2>{{ __('Stockage local') }}</h2>
                <p>{{ __('Votre navigateur peut retenir votre choix d’apparence (clair ou sombre). Rien n’est transmis à des tiers.') }}</p>
            </div>
        </div>
    </article>
</x-layouts.public>
