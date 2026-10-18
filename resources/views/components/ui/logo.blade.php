@props(['compact' => false, 'inverted' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="grid size-7 place-items-center rounded-[0.5rem] bg-relais-500 text-white">
        <x-ui.icon name="signal" class="size-4" stroke="2.25" />
    </span>
    @unless ($compact)
        <span @class(['font-display text-[0.9375rem] leading-none font-semibold tracking-[-0.01em]', 'text-white' => $inverted])>JE RELAIS</span>
    @endunless
</span>
