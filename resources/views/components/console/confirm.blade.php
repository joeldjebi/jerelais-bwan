{{-- Boîte de confirmation élégante (remplace le confirm() du navigateur), pilotée par une propriété Livewire. --}}
@props(['model', 'title', 'text' => null, 'confirmLabel' => __('Confirmer'), 'action', 'tone' => 'danger'])

<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false" x-cloak>
    <div x-show="open" x-transition.opacity.duration.250ms class="fixed inset-0 z-[65] bg-black/30 backdrop-blur-[2px]"></div>
    <div x-show="open" class="fixed inset-0 z-[66] grid place-items-center p-4" x-on:click.self="open = false">
        <div
            x-show="open"
            x-transition:enter="transition duration-400 ease-out-expo"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-end="opacity-0 scale-95"
            class="w-full max-w-sm rounded-[1.5rem] bg-surface p-6 text-center shadow-[0_30px_80px_-20px_rgb(0_0_0/0.45)] ring-1 ring-line"
            role="alertdialog"
            aria-modal="true"
        >
            <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
            @if ($text)
                <p class="mt-2 text-[0.875rem] leading-relaxed text-mute">{{ $text }}</p>
            @endif
            <div class="mt-6 grid grid-cols-2 gap-2">
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
                <x-ui.button :variant="$tone === 'danger' ? 'danger' : 'primary'" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}">{{ $confirmLabel }}</x-ui.button>
            </div>
        </div>
    </div>
</div>
