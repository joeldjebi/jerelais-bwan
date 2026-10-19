{{-- Notifications discrètes en bas à droite, alimentées par $this->dispatch('toast', message: …, tone: …). --}}
@php
    $initial = session('toast') ? [session('toast')] : [];
@endphp

<div
    x-data="toasts(@js($initial))"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-4 bottom-4 z-[70] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6"
    aria-live="polite"
>
    <template x-for="toast in items" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition duration-500 ease-out-expo"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition duration-300 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-2xl bg-onyx/95 px-4 py-3 text-[0.875rem] text-white shadow-[0_20px_50px_-20px_rgb(0_0_0/0.5)] backdrop-blur-xl dark:bg-white/95 dark:text-onyx"
        >
            <span class="grid size-6 shrink-0 place-items-center rounded-full" :class="toast.tone === 'error' ? 'bg-status-rejected' : 'bg-status-validated'">
                <svg x-show="toast.tone !== 'error'" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                <svg x-show="toast.tone === 'error'" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>
            </span>
            <p class="flex-1" x-text="toast.message"></p>
            <button type="button" x-on:click="dismiss(toast.id)" class="text-white/50 hover:text-white dark:text-onyx/40 dark:hover:text-onyx" aria-label="{{ __('Fermer') }}">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
