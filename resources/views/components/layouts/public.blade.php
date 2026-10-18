@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" :description="$description" :force-light="true" :scripts="['resources/js/public.js']" />
</head>
<body class="min-h-dvh bg-white text-onyx antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-onyx focus:px-4 focus:py-2 focus:text-white">{{ __('Aller au contenu') }}</a>

    <header class="fixed inset-x-0 top-0 z-50 text-onyx transition-[background-color,color,box-shadow] duration-500 ease-apple in-data-scrolled:glass in-data-scrolled:shadow-[0_1px_0_rgb(0_0_0/0.06)] in-data-nav-dark:text-white in-data-nav-dark:shadow-none" data-site-nav>
        <nav class="mx-auto flex h-12 max-w-[1024px] items-center justify-between px-5" aria-label="{{ __('Navigation principale') }}">
            <a href="{{ route('home') }}" aria-label="JE RELAIS — {{ __('accueil') }}">
                <x-ui.logo />
            </a>

            <div class="hidden items-center gap-8 text-[0.75rem] opacity-80 md:flex">
                <a href="#principe" class="transition-opacity duration-300 hover:opacity-100">{{ __('Le principe') }}</a>
                <a href="#terrain" class="transition-opacity duration-300 hover:opacity-100">{{ __('Sur le terrain') }}</a>
                <a href="#devenir-relais" class="transition-opacity duration-300 hover:opacity-100">{{ __('Devenir Relais') }}</a>
                <a href="#confiance" class="transition-opacity duration-300 hover:opacity-100">{{ __('Confiance') }}</a>
                <a href="#faq" class="transition-opacity duration-300 hover:opacity-100">{{ __('Questions') }}</a>
            </div>

            <a href="#devenir-relais" class="press rounded-full bg-onyx px-3.5 py-1.5 text-[0.75rem] font-medium text-white transition-colors duration-500 hover:bg-black in-data-nav-dark:bg-white in-data-nav-dark:text-onyx">{{ __('Rejoindre') }}</a>
        </nav>
    </header>

    <main id="contenu">
        {{ $slot }}
    </main>

    <footer class="bg-mist text-[0.75rem] text-graphite">
        <div class="mx-auto max-w-[1024px] px-5 py-12">
            <p class="max-w-2xl leading-relaxed">{{ __('JE RELAIS est la plateforme des observateurs citoyens de REFLET TV, chaîne de télévision ivoirienne diffusée sur le bouquet CANAL+. Les contenus transmis sont vérifiés par un coordonnateur communal avant toute diffusion.') }}</p>
            <div class="mt-8 grid gap-8 border-t border-black/10 pt-8 sm:grid-cols-3">
                <div>
                    <p class="font-semibold text-onyx">{{ __('Découvrir') }}</p>
                    <ul class="mt-3 space-y-2">
                        <li><a href="#principe" class="hover:text-onyx hover:underline">{{ __('Le principe') }}</a></li>
                        <li><a href="#terrain" class="hover:text-onyx hover:underline">{{ __('Sur le terrain') }}</a></li>
                        <li><a href="#faq" class="hover:text-onyx hover:underline">{{ __('Questions fréquentes') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-onyx">{{ __('Rejoindre') }}</p>
                    <ul class="mt-3 space-y-2">
                        <li><a href="#devenir-relais" class="hover:text-onyx hover:underline">{{ __('Devenir Relais') }}</a></li>
                        <li><span>{{ __('Charte du Relais') }}</span></li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-onyx">{{ __('Informations légales') }}</p>
                    <ul class="mt-3 space-y-2">
                        <li><span>{{ __('Conditions d’utilisation') }}</span></li>
                        <li><span>{{ __('Politique de confidentialité') }}</span></li>
                        <li><span>{{ __('Mentions légales') }}</span></li>
                    </ul>
                </div>
            </div>
            <p class="mt-10 border-t border-black/10 pt-6">© {{ now()->year }} REFLET TV. {{ __('Tous droits réservés.') }}</p>
        </div>
    </footer>
</body>
</html>
