@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'default', 'href' => null])

@php
    $iconTone = [
        'default' => 'bg-ink/[0.05] text-ink-soft',
        'accent' => 'bg-relais-500/12 text-relais-600',
        'success' => 'bg-status-validated/12 text-status-validated',
        'alert' => 'bg-status-rejected/12 text-status-rejected',
    ][$tone];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'group relative block rounded-[1.25rem] bg-surface p-5 ring-1 ring-line shadow-[0_1px_2px_rgb(0_0_0/0.03)] transition duration-500 ease-out-expo'.($href ? ' hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-16px_rgb(0_0_0/0.18)]' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[0.8125rem] font-medium text-mute">{{ $label }}</p>
        @if ($icon)
            <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $iconTone }}">
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>
    <p class="mt-3 font-display text-[2rem] leading-none font-semibold tracking-tight tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-2 text-xs text-mute">{{ $hint }}</p>
    @endif
    @if ($href)
        <x-ui.icon name="arrow-up-right" class="absolute right-5 bottom-5 size-4 text-mute opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
    @endif
</{{ $tag }}>
