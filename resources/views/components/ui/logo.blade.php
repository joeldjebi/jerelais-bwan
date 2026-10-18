@props(['compact' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="relative grid size-8 place-items-center rounded-[0.6rem] bg-linear-to-br from-relais-400 to-relais-600 text-white shadow-[0_6px_16px_-6px_var(--color-relais-500)]">
        <x-ui.icon name="signal" class="size-[1.15rem]" stroke="2" />
    </span>
    @unless ($compact)
        <span class="font-display text-[1.0625rem] leading-none font-semibold tracking-tight">
            JE RELAIS
            <span class="mt-0.5 block text-[0.625rem] font-medium tracking-[0.14em] text-mute uppercase">{{ __('par REFLET TV') }}</span>
        </span>
    @endunless
</span>
