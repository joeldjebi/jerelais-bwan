{{-- Feuille modale façon iOS / macOS : monte du bas sur mobile, apparaît au centre avec un ressort sur grand écran. --}}
@props(['model', 'title', 'description' => null])

<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false" x-cloak>
    <div x-show="open" x-transition.opacity.duration.300ms class="fixed inset-0 z-[55] bg-black/30 backdrop-blur-[3px]" x-on:click="open = false"></div>

    <div x-show="open" class="fixed inset-0 z-[56] flex items-end justify-center sm:items-center sm:p-6" x-on:click.self="open = false">
        <div
            x-show="open"
            x-transition:enter="transition duration-500 ease-spring"
            x-transition:enter-start="opacity-0 translate-y-16 sm:translate-y-6 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-10 sm:translate-y-2 sm:scale-[0.97]"
            class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[1.6rem] bg-canvas shadow-[0_40px_100px_-30px_rgb(0_0_0/0.5)] sm:max-w-lg sm:rounded-[1.6rem] sm:shadow-[0_0_0_0.5px_rgb(0_0_0/0.08),0_40px_100px_-30px_rgb(0_0_0/0.5)] dark:sm:shadow-[0_0_0_0.5px_rgb(255_255_255/0.1),0_40px_100px_-30px_rgb(0_0_0/0.9)]"
            role="dialog"
            aria-modal="true"
            aria-label="{{ $title }}"
        >
            <span class="mx-auto mt-2 h-1.5 w-10 rounded-full bg-black/15 sm:hidden dark:bg-white/20" aria-hidden="true"></span>
            <header class="flex items-start justify-between gap-4 px-6 pt-5 pb-4">
                <div>
                    <h2 class="text-[1.375rem] font-bold tracking-[-0.02em]">{{ $title }}</h2>
                    @if ($description)
                        <p class="mt-1 text-[0.875rem] text-mute">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" x-on:click="open = false" class="press grid size-8 shrink-0 place-items-center rounded-full bg-black/[0.06] text-mute hover:text-ink dark:bg-white/10" aria-label="{{ __('Fermer') }}">
                    <x-ui.icon name="x" class="size-4" stroke="2.5" />
                </button>
            </header>
            <div class="flex-1 overflow-y-auto px-6 pb-6">
                {{ $slot }}
            </div>
            @isset($footer)
                <footer class="flex items-center justify-end gap-2 border-t border-line bg-canvas/90 px-6 py-4 backdrop-blur-xl">{{ $footer }}</footer>
            @endisset
        </div>
    </div>
</div>
