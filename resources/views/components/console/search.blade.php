{{-- Champ de recherche façon iOS (fond gris, loupe, sans bordure). --}}
@props(['placeholder' => __('Rechercher')])

<label class="relative block min-w-[12rem] flex-1">
    <span class="sr-only">{{ $placeholder }}</span>
    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-[0.95rem] -translate-y-1/2 text-mute" />
    <input type="search" placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'h-9 w-full rounded-[0.65rem] border-0 bg-black/[0.055] pr-3 pl-9 text-[0.9375rem] placeholder:text-mute focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]']) }}>
</label>
