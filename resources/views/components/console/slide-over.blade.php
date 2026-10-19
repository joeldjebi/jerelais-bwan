{{-- Panneau latéral (formulaires de création/édition), piloté par une propriété Livewire booléenne. --}}
@props(['model', 'title', 'description' => null])

<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false" x-cloak>
    <div x-show="open" x-transition.opacity.duration.300ms class="fixed inset-0 z-[55] bg-black/25 backdrop-blur-[2px]" x-on:click="open = false"></div>

    <aside
        x-show="open"
        x-transition:enter="transition duration-500 ease-out-expo"
        x-transition:enter-start="translate-x-full opacity-60"
        x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-[56] flex w-full max-w-md flex-col bg-surface shadow-[0_0_80px_-20px_rgb(0_0_0/0.4)] sm:inset-y-3 sm:right-3 sm:rounded-[1.5rem] sm:ring-1 sm:ring-line"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $title }}"
    >
        <header class="flex items-start justify-between gap-4 border-b border-line px-6 py-5">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1 text-[0.8125rem] text-mute">{{ $description }}</p>
                @endif
            </div>
            <button type="button" x-on:click="open = false" class="press grid size-8 place-items-center rounded-full text-mute hover:bg-ink/5 hover:text-ink" aria-label="{{ __('Fermer') }}">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </header>
        <div class="flex-1 overflow-y-auto px-6 py-6">
            {{ $slot }}
        </div>
        @isset($footer)
            <footer class="flex items-center justify-end gap-2 border-t border-line px-6 py-4">{{ $footer }}</footer>
        @endisset
    </aside>
</div>
