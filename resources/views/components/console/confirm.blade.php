{{-- Alerte de confirmation façon iOS : compacte, centrée, boutons séparés par des filets. --}}
@props(['model', 'title', 'text' => null, 'confirmLabel' => __('Confirmer'), 'action', 'tone' => 'danger'])

<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-on:keydown.escape.window="open = false" x-cloak>
    <div x-show="open" x-transition.opacity.duration.250ms class="fixed inset-0 z-[65] bg-black/30 backdrop-blur-[3px]"></div>
    <div x-show="open" class="fixed inset-0 z-[66] grid place-items-center p-6" x-on:click.self="open = false">
        <div
            x-show="open"
            x-transition:enter="transition duration-400 ease-spring"
            x-transition:enter-start="opacity-0 scale-110"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-end="opacity-0 scale-95"
            class="w-full max-w-[17.5rem] overflow-hidden rounded-[1.1rem] bg-white/85 text-center shadow-[0_30px_80px_-20px_rgb(0_0_0/0.5)] backdrop-blur-2xl backdrop-saturate-150 dark:bg-[#2c2c2e]/90"
            role="alertdialog"
            aria-modal="true"
        >
            <div class="px-5 pt-5 pb-4">
                <h2 class="text-[1.0625rem] font-semibold tracking-[-0.01em]">{{ $title }}</h2>
                @if ($text)
                    <p class="mt-1.5 text-[0.8125rem] leading-snug text-ink-soft">{{ $text }}</p>
                @endif
            </div>
            <div class="grid grid-cols-2 border-t border-black/10 dark:border-white/10">
                <button type="button" x-on:click="open = false" class="py-3 text-[1.0625rem] text-tile-blue transition-colors hover:bg-black/[0.04] dark:hover:bg-white/[0.05]">{{ __('Annuler') }}</button>
                <button type="button" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}"
                    @class([
                        'border-l border-black/10 py-3 text-[1.0625rem] font-semibold transition-colors hover:bg-black/[0.04] disabled:opacity-50 dark:border-white/10 dark:hover:bg-white/[0.05]',
                        'text-status-rejected' => $tone === 'danger',
                        'text-tile-blue' => $tone !== 'danger',
                    ])>{{ $confirmLabel }}</button>
            </div>
        </div>
    </div>
</div>
