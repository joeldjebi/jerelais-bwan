{{-- Interrupteur iOS déclenchant une action Livewire (l'état réel est confirmé côté serveur). --}}
@props(['on' => false, 'label'])

<button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'relative inline-flex h-[1.9rem] w-[3.1rem] shrink-0 items-center rounded-full transition-colors duration-300 '.($on ? 'bg-tile-green' : 'bg-black/[0.12] dark:bg-white/[0.16]')]) }}>
    <span @class([
        'absolute left-[0.125rem] size-[1.65rem] rounded-full bg-white shadow-[0_3px_8px_rgb(0_0_0/0.15),0_0_0_0.5px_rgb(0_0_0/0.04)] transition-transform duration-500 ease-spring',
        'translate-x-[1.2rem]' => $on,
    ])></span>
</button>
