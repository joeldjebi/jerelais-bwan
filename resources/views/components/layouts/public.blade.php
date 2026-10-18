@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-20">
<head>
    <x-layouts.head :title="$title" :description="$description" />
</head>
<body class="min-h-dvh">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-canvas">{{ __('Aller au contenu') }}</a>

    <header class="fixed inset-x-0 top-0 z-40 transition-[background-color,box-shadow] duration-500 ease-apple in-data-scrolled:glass in-data-scrolled:shadow-[0_1px_0_var(--line)]">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 transition-[height] duration-500 ease-out-expo in-data-scrolled:h-14 sm:px-8" aria-label="{{ __('Navigation principale') }}">
            <a href="{{ route('home') }}" class="press" aria-label="JE RELAIS — {{ __('accueil') }}">
                <x-ui.logo />
            </a>

            <div class="hidden items-center gap-1 text-[0.875rem] md:flex">
                <a href="#principe" class="rounded-full px-3.5 py-2 text-ink-soft transition-colors hover:text-ink">{{ __('Le principe') }}</a>
                <a href="#devenir-relais" class="rounded-full px-3.5 py-2 text-ink-soft transition-colors hover:text-ink">{{ __('Devenir Relais') }}</a>
                <a href="#confiance" class="rounded-full px-3.5 py-2 text-ink-soft transition-colors hover:text-ink">{{ __('Confiance') }}</a>
                <a href="#faq" class="rounded-full px-3.5 py-2 text-ink-soft transition-colors hover:text-ink">{{ __('Questions') }}</a>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="toggleTheme()" class="press grid size-9 place-items-center rounded-full text-ink-soft transition-colors hover:bg-ink/5 hover:text-ink" aria-label="{{ __('Changer de thème') }}">
                    <x-ui.icon name="moon" class="size-[1.15rem] dark:hidden" />
                    <x-ui.icon name="sun" class="hidden size-[1.15rem] dark:block" />
                </button>
                <x-ui.button href="#devenir-relais" size="sm" class="hidden sm:inline-flex">{{ __('Devenir Relais') }}</x-ui.button>
            </div>
        </nav>
    </header>

    <main id="contenu">
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-ui.logo />
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-mute">{{ __('JE RELAIS est la plateforme des observateurs citoyens de REFLET TV, chaîne ivoirienne diffusée sur le bouquet CANAL+.') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-ink uppercase">{{ __('Plateforme') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-mute">
                    <li><a href="#principe" class="hover:text-ink">{{ __('Le principe') }}</a></li>
                    <li><a href="#devenir-relais" class="hover:text-ink">{{ __('Devenir Relais') }}</a></li>
                    <li><a href="#faq" class="hover:text-ink">{{ __('Questions fréquentes') }}</a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-ink uppercase">{{ __('Informations légales') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-mute">
                    <li><span>{{ __('Charte du Relais') }}</span></li>
                    <li><span>{{ __('Conditions d’utilisation') }}</span></li>
                    <li><span>{{ __('Politique de confidentialité') }}</span></li>
                </ul>
            </div>
        </div>
        <div class="mx-auto max-w-6xl border-t border-line px-5 py-6 text-xs text-mute sm:px-8">
            © {{ now()->year }} REFLET TV. {{ __('Tous droits réservés.') }}
        </div>
    </footer>
</body>
</html>
