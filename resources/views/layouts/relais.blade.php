{{-- Espace candidat et Relais sur le site (WEB-02, WEB-03) : clair, sobre, mêmes codes que le site public. --}}
@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title ?? null" :force-light="true" :scripts="['resources/js/relais.js']" />
    @livewireStyles
</head>
<body class="min-h-dvh bg-mist text-onyx antialiased">
    <header class="glass sticky top-0 z-40 shadow-[0_1px_0_rgb(0_0_0/0.06)]">
        <nav class="mx-auto flex h-14 max-w-[1080px] items-center justify-between px-5" aria-label="{{ __('Navigation') }}">
            <a href="{{ route('home') }}" aria-label="JE RELAIS — {{ __('accueil') }}"><x-ui.logo /></a>

            @if ($user)
                <div class="flex items-center gap-4 text-[0.8125rem]">
                    <span class="hidden text-graphite sm:inline">{{ $user->phoneNumber()?->masked() }}</span>
                    <form method="POST" action="{{ route('relais.logout') }}">
                        @csrf
                        <button type="submit" class="press rounded-full bg-black/[0.05] px-3.5 py-1.5 font-medium transition-colors duration-300 hover:bg-black/[0.09]">{{ __('Se déconnecter') }}</button>
                    </form>
                </div>
            @else
                <a href="{{ route('home') }}" class="text-[0.8125rem] text-graphite transition-colors hover:text-onyx">{{ __('Retour au site') }}</a>
            @endif
        </nav>
    </header>

    <main id="contenu" class="px-5 pt-10 pb-24 sm:pt-14">
        {{ $slot }}
    </main>

    <footer class="pb-10 text-center text-[0.75rem] text-graphite">
        {{ __('JE RELAIS · les observateurs citoyens de REFLET TV') }}
    </footer>

    @livewireScriptConfig
</body>
</html>
