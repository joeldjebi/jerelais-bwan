{{-- Coquille commune au back-office coordonnateur et à la console REFLET TV. --}}
@props(['title' => null, 'section' => 'console', 'active' => null, 'scope' => null, 'userName' => '', 'userRole' => '', 'queueCount' => 0])

@php
    $navigation = $section === 'coordinateur'
        ? [
            ['inbox', __('À valider'), 'queue'],
            ['users', __('Candidatures'), 'candidatures'],
            ['map-pin', __('Mes Relais'), 'relais'],
            ['bell', __('Messages'), 'messages'],
            ['chart', __('Tableau de bord'), 'dashboard'],
        ]
        : [
            ['signal', __('Direct'), 'live'],
            ['map-pin', __('Carte'), 'map'],
            ['layers', __('Relais'), 'relais'],
            ['bolt', __('Appels à relais'), 'appels'],
            ['photo', __('Collections'), 'collections'],
            ['users', __('Réseau'), 'reseau'],
            ['chart', __('Rapports'), 'rapports'],
        ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-dvh bg-canvas">
    <div class="flex min-h-dvh">
        <aside class="glass sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-line px-4 py-5 lg:flex">
            <x-ui.logo class="px-2" />

            <p class="mt-8 px-3 text-[0.6875rem] font-semibold tracking-[0.12em] text-mute uppercase">
                {{ $scope ?? ($section === 'coordinateur' ? __('Coordination') : __('Console REFLET TV')) }}
            </p>

            <nav class="mt-3 flex flex-col gap-0.5" aria-label="{{ __('Navigation de la console') }}">
                @foreach ($navigation as [$icon, $label, $key])
                    <a href="#" @class([
                        'press group flex items-center gap-3 rounded-xl px-3 py-2 text-[0.875rem] font-medium transition-colors duration-300',
                        'bg-ink text-canvas shadow-sm' => $active === $key,
                        'text-ink-soft hover:bg-ink/5 hover:text-ink' => $active !== $key,
                    ])>
                        <x-ui.icon :name="$icon" class="size-[1.05rem]" />
                        {{ $label }}
                        @if ($key === 'queue' && $queueCount > 0)
                            <span class="ml-auto rounded-full bg-relais-500 px-2 py-0.5 text-[0.6875rem] font-semibold text-white tabular-nums">{{ $queueCount }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto flex items-center gap-3 rounded-2xl p-2 ring-1 ring-line">
                <span class="grid size-9 place-items-center rounded-full bg-linear-to-br from-navy-400 to-navy-700 text-sm font-semibold text-white">{{ Str::of($userName)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('') }}</span>
                <div class="min-w-0 text-sm">
                    <p class="truncate font-medium">{{ $userName }}</p>
                    <p class="truncate text-xs text-mute">{{ $userRole }}</p>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="glass sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-line px-5 sm:px-8">
                <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-ink/5 lg:hidden" aria-label="{{ __('Menu') }}">
                    <x-ui.icon name="menu" />
                </button>

                <label class="relative hidden max-w-md flex-1 sm:block">
                    <span class="sr-only">{{ __('Rechercher') }}</span>
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-mute" />
                    <input type="search" placeholder="{{ __('Rechercher un relais, une commune, un Relais…') }}" class="h-10 w-full rounded-full bg-ink/[0.05] pr-14 pl-10 text-sm placeholder:text-mute focus:bg-surface focus:ring-2 focus:ring-relais-500/40 focus:outline-none">
                    <kbd class="absolute top-1/2 right-3 -translate-y-1/2 rounded-md bg-surface px-1.5 py-0.5 font-sans text-[0.6875rem] text-mute ring-1 ring-line">⌘K</kbd>
                </label>

                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden items-center gap-2 rounded-full bg-status-validated/10 px-3 py-1.5 text-xs font-semibold text-status-validated sm:inline-flex">
                        <span class="size-1.5 animate-pulse-dot rounded-full bg-current"></span>{{ __('Temps réel') }}
                    </span>
                    <button type="button" class="press relative grid size-9 place-items-center rounded-full text-ink-soft hover:bg-ink/5" aria-label="{{ __('Alertes') }}">
                        <x-ui.icon name="bell" class="size-[1.15rem]" />
                        <span class="absolute top-1.5 right-1.5 size-2 rounded-full bg-status-rejected ring-2 ring-canvas"></span>
                    </button>
                    <button type="button" onclick="toggleTheme()" class="press grid size-9 place-items-center rounded-full text-ink-soft hover:bg-ink/5" aria-label="{{ __('Changer de thème') }}">
                        <x-ui.icon name="moon" class="size-[1.15rem] dark:hidden" />
                        <x-ui.icon name="sun" class="hidden size-[1.15rem] dark:block" />
                    </button>
                </div>
            </header>

            <main class="flex-1 px-5 py-8 sm:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
