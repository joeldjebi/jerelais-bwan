{{-- Coquille de la console REFLET TV et du back-office coordonnateur (pages Livewire). --}}
@php
    use App\Domain\Access\Enums\Role;
    use App\Livewire\Console\Support\Navigation;

    /** @var \App\Models\User $user */
    $user = auth()->user()->loadMissing('communes');
    $navigation = Navigation::for($user);
    $roleName = $user->getRoleNames()->first();
    $roleLabel = $roleName ? Role::from($roleName)->label() : '';
    $isCoordination = $user->hasAnyRole([Role::Coordonnateur->value, Role::Superviseur->value]);
    $scope = $isCoordination ? $user->communes->pluck('name')->join(', ') ?: __('Aucune commune') : __('National');
    $initials = Str::of($user->name ?? $user->email ?? '?')->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-layouts.head :title="$title ?? null" :scripts="['resources/js/console.js']" />
    @livewireStyles
</head>
<body class="min-h-full bg-mist text-ink antialiased dark:bg-black" x-data="{ menu: false }">
    {{-- Barre latérale (persistée entre les pages : aucune saccade à la navigation) --}}
    @persist('console-sidebar')
        <div
            x-show="menu" x-transition.opacity.duration.300ms x-on:click="menu = false"
            class="fixed inset-0 z-40 bg-black/25 backdrop-blur-[2px] lg:hidden" x-cloak
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[17rem] -translate-x-full flex-col px-3 py-4 transition-transform duration-500 ease-out-expo lg:translate-x-0"
            :class="menu ? 'translate-x-0 bg-mist dark:bg-black shadow-2xl' : '-translate-x-full lg:translate-x-0'"
            x-on:click.outside="menu = false"
        >
            <div class="flex items-center justify-between px-3 pt-1">
                <a href="{{ route('console.home') }}" wire:navigate aria-label="{{ __('Accueil de la console') }}"><x-ui.logo /></a>
                <button type="button" class="grid size-8 place-items-center rounded-full text-mute hover:bg-ink/5 lg:hidden" x-on:click="menu = false" aria-label="{{ __('Fermer le menu') }}">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            {{-- Espace de travail --}}
            <div class="mx-1 mt-6 rounded-2xl bg-surface p-3.5 ring-1 ring-line">
                <p class="text-[0.6875rem] font-semibold tracking-[0.08em] text-mute uppercase">{{ $isCoordination ? __('Coordination') : __('Console REFLET TV') }}</p>
                <p class="mt-1 flex items-center gap-1.5 truncate text-[0.875rem] font-semibold">
                    <x-ui.icon :name="$isCoordination ? 'map-pin' : 'signal'" class="size-4 text-relais-500" />
                    <span class="truncate">{{ $scope }}</span>
                </p>
            </div>

            <nav class="mt-6 flex-1 space-y-6 overflow-y-auto px-1" aria-label="{{ __('Navigation de la console') }}">
                @foreach ($navigation as $group)
                    <div>
                        <p class="px-3 text-[0.6875rem] font-semibold tracking-[0.08em] text-mute uppercase">{{ $group['label'] }}</p>
                        <ul class="mt-2 space-y-0.5">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @if ($item['route'] === 'console.home') wire:current.exact="is-active" @else wire:current="is-active" @endif
                                        x-on:click="menu = false"
                                        class="nav-link press flex items-center gap-3 rounded-xl px-3 py-2 text-[0.875rem] font-medium text-ink-soft transition-[background-color,color,box-shadow] duration-300 hover:bg-ink/[0.04] hover:text-ink"
                                    >
                                        <x-ui.icon :name="$item['icon']" class="nav-icon size-[1.1rem] transition-colors duration-300" />
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            {{-- Compte --}}
            <div class="relative mx-1 mt-4" x-data="{ open: false }" x-on:click.outside="open = false">
                <div
                    x-show="open" x-cloak
                    x-transition:enter="transition duration-300 ease-out-expo" x-transition:enter-start="opacity-0 translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0 scale-95"
                    class="absolute inset-x-0 bottom-full mb-2 origin-bottom overflow-hidden rounded-2xl bg-surface p-1.5 shadow-[0_20px_50px_-20px_rgb(0_0_0/0.35)] ring-1 ring-line"
                >
                    <a href="{{ route('console.account') }}" wire:navigate x-on:click="open = false; menu = false" class="flex items-center gap-3 rounded-xl px-3 py-2 text-[0.875rem] hover:bg-ink/[0.05]">
                        <x-ui.icon name="users" class="size-4 text-mute" />{{ __('Mon compte') }}
                    </a>
                    <a href="{{ route('console.security') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-[0.875rem] hover:bg-ink/[0.05]">
                        <x-ui.icon name="shield" class="size-4 text-mute" />{{ __('Sécurité') }}
                    </a>
                    <div class="mx-3 my-1.5 border-t border-line"></div>
                    <div class="px-3 py-1.5">
                        <p class="text-[0.6875rem] font-semibold tracking-[0.08em] text-mute uppercase">{{ __('Apparence') }}</p>
                        <div class="mt-2 grid grid-cols-3 gap-1 rounded-xl bg-ink/[0.05] p-1 text-xs font-medium" x-data="{ theme: localStorage.getItem('jerelais.theme') ?? 'system' }">
                            @foreach (['light' => __('Clair'), 'dark' => __('Sombre'), 'system' => __('Auto')] as $value => $label)
                                <button type="button"
                                    x-on:click="theme = '{{ $value }}'; '{{ $value }}' === 'system' ? localStorage.removeItem('jerelais.theme') : localStorage.setItem('jerelais.theme', '{{ $value }}'); document.documentElement.classList.toggle('dark', '{{ $value }}' === 'dark' || ('{{ $value }}' === 'system' && matchMedia('(prefers-color-scheme: dark)').matches))"
                                    :class="theme === '{{ $value }}' ? 'bg-surface shadow-sm text-ink' : 'text-mute'"
                                    class="rounded-lg py-1.5 transition-colors duration-300">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="mx-3 my-1.5 border-t border-line"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-[0.875rem] text-status-rejected hover:bg-status-rejected/[0.08]">
                            <x-ui.icon name="log-out" class="size-4" />{{ __('Se déconnecter') }}
                        </button>
                    </form>
                </div>

                <button type="button" x-on:click="open = !open" class="press flex w-full items-center gap-3 rounded-2xl p-2 text-left transition-colors duration-300 hover:bg-ink/[0.04]" :class="open && 'bg-ink/[0.04]'">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-navy-700 text-[0.8125rem] font-semibold text-white">{{ $initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[0.875rem] font-semibold">{{ $user->name ?? $user->email }}</span>
                        <span class="block truncate text-xs text-mute">{{ $roleLabel }}</span>
                    </span>
                    <svg class="size-4 shrink-0 text-mute transition-transform duration-300" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m7 14 5-5 5 5"/></svg>
                </button>
            </div>
        </aside>
    @endpersist

    {{-- Zone de contenu --}}
    <div class="lg:pl-[17rem]">
        <div class="min-h-dvh bg-canvas lg:my-2 lg:mr-2 lg:min-h-[calc(100dvh-1rem)] lg:rounded-[1.5rem] lg:ring-1 lg:ring-line dark:bg-[#111113]">
            <header class="glass sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line px-4 sm:px-8 lg:rounded-t-[1.5rem]">
                <button type="button" class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-ink/5 lg:hidden" x-on:click="menu = true" aria-label="{{ __('Ouvrir le menu') }}">
                    <x-ui.icon name="menu" class="size-5" />
                </button>

                <p class="truncate text-[0.875rem] text-mute">
                    <span class="hidden sm:inline">{{ $isCoordination ? __('Coordination') : __('Console') }}</span>
                    @isset($title)<span class="hidden sm:inline"> / </span><span class="font-medium text-ink">{{ $title }}</span>@endisset
                </p>

                <button type="button" x-on:click="$dispatch('palette-open')" class="ml-auto flex h-9 items-center gap-2 rounded-full bg-ink/[0.05] pr-2 pl-3 text-[0.8125rem] text-mute transition-colors duration-300 hover:bg-ink/[0.08] sm:w-72">
                    <x-ui.icon name="search" class="size-4" />
                    <span class="hidden sm:inline">{{ __('Rechercher…') }}</span>
                    <kbd class="ml-auto hidden rounded-md bg-surface px-1.5 py-0.5 font-sans text-[0.6875rem] ring-1 ring-line sm:inline">⌘K</kbd>
                </button>
            </header>

            <main class="px-4 pt-8 pb-20 sm:px-8 lg:px-12">
                <div class="page-enter mx-auto max-w-6xl space-y-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <x-console.command-palette :commands="Navigation::commands($user)" />
    <x-console.toasts />

    @livewireScriptConfig
</body>
</html>
