{{-- Coquille de la console REFLET TV et du back-office coordonnateur, façon macOS (pages Livewire). --}}
@php
    use App\Domain\Access\Enums\Role;
    use App\Livewire\Console\Support\Navigation;

    /** @var \App\Models\User $user */
    $user = auth()->user()->loadMissing('communes');
    $navigation = Navigation::for($user);
    $roleName = $user->getRoleNames()->first();
    $roleLabel = $roleName ? Role::from($roleName)->label() : '';
    $isCoordination = $user->hasAnyRole([Role::Coordonnateur->value, Role::Superviseur->value]);
    $scope = $isCoordination ? $user->communes->pluck('name')->join(', ') ?: __('Aucune commune') : __('Réseau national');
    $initials = Str::of($user->name ?? $user->email ?? '?')->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-default-theme="light">
<head>
    <x-layouts.head :title="$title ?? null" :scripts="['resources/js/console.js']" default-theme="light" />
    @livewireStyles
</head>
<body class="min-h-full bg-canvas text-ink antialiased" x-data="{ menu: false }">
    @persist('console-sidebar')
        <div x-show="menu" x-transition.opacity.duration.300ms x-on:click="menu = false" class="fixed inset-0 z-40 bg-black/25 backdrop-blur-[2px] lg:hidden" x-cloak></div>

        {{-- Barre latérale flottante en verre dépoli --}}
        <aside
            class="fixed top-3 bottom-3 left-3 z-50 flex w-[16.5rem] flex-col rounded-[1.4rem] bg-white/70 shadow-[0_0_0_0.5px_rgb(0_0_0/0.08),0_20px_50px_-20px_rgb(0_0_0/0.18)] backdrop-blur-2xl backdrop-saturate-150 transition-transform duration-500 ease-spring dark:bg-[#1c1c1e]/75 dark:shadow-[0_0_0_0.5px_rgb(255_255_255/0.08),0_20px_50px_-20px_rgb(0_0_0/0.8)] lg:translate-x-0"
            :class="menu ? 'translate-x-0' : '-translate-x-[110%] lg:translate-x-0'"
            x-on:click.outside="menu = false"
        >
            <div class="flex items-center justify-between px-5 pt-5">
                <a href="{{ route('console.home') }}" wire:navigate aria-label="{{ __('Accueil de la console') }}"><x-ui.logo /></a>
                <button type="button" class="grid size-7 place-items-center rounded-full text-mute hover:bg-black/5 lg:hidden" x-on:click="menu = false" aria-label="{{ __('Fermer le menu') }}">
                    <x-ui.icon name="x" class="size-4" />
                </button>
            </div>

            <button type="button" x-on:click="$dispatch('palette-open')" class="mx-3 mt-5 flex h-9 items-center gap-2 rounded-[0.65rem] bg-black/[0.05] px-3 text-[0.8125rem] text-mute transition-colors duration-300 hover:bg-black/[0.08] dark:bg-white/[0.07] dark:hover:bg-white/[0.11]">
                <x-ui.icon name="search" class="size-[0.95rem]" />
                {{ __('Rechercher') }}
                <kbd class="ml-auto font-sans text-[0.6875rem] text-mute/80">⌘K</kbd>
            </button>

            <nav class="relative mt-4 flex-1 overflow-y-auto px-3 pb-3" aria-label="{{ __('Navigation de la console') }}" data-nav>
                <div class="pointer-events-none absolute top-0 right-3 left-3 rounded-[0.65rem] bg-black/[0.07] opacity-0 dark:bg-white/[0.1]" data-nav-indicator aria-hidden="true"></div>

                @foreach ($navigation as $group)
                    <p class="px-3 pt-4 pb-1.5 text-[0.6875rem] font-semibold text-mute">{{ $group['label'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    wire:navigate
                                    @if ($item['route'] === 'console.home') wire:current.exact="is-active" @else wire:current="is-active" @endif
                                    x-on:click="menu = false"
                                    class="nav-link relative flex items-center gap-2.5 rounded-[0.65rem] px-2.5 py-1.5 text-[0.875rem] font-medium text-ink-soft transition-colors duration-200 hover:bg-black/[0.035] dark:hover:bg-white/[0.05]"
                                >
                                    <x-console.tile :icon="$item['icon']" :tint="$item['tint']" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            {{-- Compte --}}
            <div class="relative p-3" x-data="{ open: false, theme: localStorage.getItem('jerelais.theme') ?? 'light' }" x-on:click.outside="open = false">
                <div
                    x-show="open" x-cloak
                    x-transition:enter="transition duration-400 ease-spring" x-transition:enter-start="opacity-0 translate-y-3 scale-90" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0 scale-95"
                    class="absolute inset-x-3 bottom-full mb-2 origin-bottom-left overflow-hidden rounded-2xl bg-white/90 p-1.5 shadow-[0_0_0_0.5px_rgb(0_0_0/0.1),0_24px_60px_-16px_rgb(0_0_0/0.3)] backdrop-blur-2xl dark:bg-[#2c2c2e]/90"
                >
                    <a href="{{ route('console.account') }}" wire:navigate x-on:click="open = false; menu = false" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[0.875rem] hover:bg-black/[0.05] dark:hover:bg-white/[0.07]">
                        <x-console.tile icon="users" tint="teal" size="sm" />{{ __('Mon compte') }}
                    </a>
                    <a href="{{ route('console.security') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[0.875rem] hover:bg-black/[0.05] dark:hover:bg-white/[0.07]">
                        <x-console.tile icon="shield" tint="pink" size="sm" />{{ __('Sécurité') }}
                    </a>
                    <div class="px-2.5 pt-2.5 pb-1.5">
                        <div class="grid grid-cols-3 gap-0.5 rounded-[0.6rem] bg-black/[0.06] p-0.5 text-xs font-medium dark:bg-white/[0.08]">
                            @foreach (['light' => __('Clair'), 'dark' => __('Sombre'), 'system' => __('Auto')] as $value => $label)
                                <button type="button" x-on:click="theme = '{{ $value }}'; setTheme('{{ $value }}')"
                                    :class="theme === '{{ $value }}' ? 'bg-white text-ink shadow-[0_1px_3px_rgb(0_0_0/0.15)] dark:bg-[#636366]' : 'text-mute'"
                                    class="rounded-[0.45rem] py-1 transition-all duration-300 ease-spring">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="mx-2.5 my-1 h-px bg-black/[0.06] dark:bg-white/[0.08]"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-[0.875rem] text-status-rejected hover:bg-status-rejected/10">
                            <span class="grid size-6 place-items-center"><x-ui.icon name="log-out" class="size-4" /></span>{{ __('Se déconnecter') }}
                        </button>
                    </form>
                </div>

                <button type="button" x-on:click="open = !open" class="press flex w-full items-center gap-2.5 rounded-xl p-2 text-left transition-colors duration-300 hover:bg-black/[0.04] dark:hover:bg-white/[0.06]" :class="open && 'bg-black/[0.04] dark:bg-white/[0.06]'">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-[#1d1d1f] text-[0.75rem] font-semibold text-white dark:bg-white dark:text-[#1d1d1f]">{{ $initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[0.8125rem] font-semibold">{{ $user->name ?? $user->email }}</span>
                        <span class="block truncate text-[0.75rem] text-mute">{{ $roleLabel }}</span>
                    </span>
                    <svg class="size-3.5 shrink-0 text-mute" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m8 10 4-4 4 4M8 14l4 4 4-4"/></svg>
                </button>
            </div>
        </aside>
    @endpersist

    <div class="lg:pl-[17.25rem]">
        {{-- Barre d'outils --}}
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 px-4 sm:px-8 lg:px-10">
            <div class="absolute inset-0 -z-10 bg-canvas/75 backdrop-blur-xl backdrop-saturate-150" aria-hidden="true"></div>
            <button type="button" class="grid size-9 place-items-center rounded-full text-ink-soft hover:bg-black/5 lg:hidden" x-on:click="menu = true" aria-label="{{ __('Ouvrir le menu') }}">
                <x-ui.icon name="menu" class="size-5" />
            </button>
            <p class="truncate text-[0.8125rem] font-medium text-mute">{{ $scope }}</p>
            <div class="ml-auto flex items-center gap-1.5">
                <span class="hidden items-center gap-2 rounded-full bg-white/80 py-1 pr-3 pl-1.5 text-[0.75rem] font-medium shadow-[0_0_0_0.5px_rgb(0_0_0/0.08)] sm:flex dark:bg-white/10">
                    <span class="relative flex size-4 items-center justify-center"><span class="absolute size-2 animate-ping rounded-full bg-status-validated/50"></span><span class="relative size-2 rounded-full bg-status-validated"></span></span>
                    {{ __('Opérationnel') }}
                </span>
                <button type="button" x-on:click="$dispatch('palette-open')" class="grid size-9 place-items-center rounded-full text-ink-soft transition-colors hover:bg-black/5 lg:hidden" aria-label="{{ __('Rechercher') }}">
                    <x-ui.icon name="search" class="size-[1.1rem]" />
                </button>
            </div>
        </header>

        <main class="px-4 pt-4 pb-24 sm:px-8 lg:px-10" data-console-main>
            <div class="mx-auto max-w-[72rem]">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-console.command-palette :commands="Navigation::commands($user)" />
    <x-console.toasts />

    @livewireScriptConfig(['nonce' => Vite::cspNonce()])
</body>
</html>
