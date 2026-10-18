@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
])

@php
    $base = 'press group/btn inline-flex items-center justify-center gap-2 rounded-(--radius-pill) font-medium whitespace-nowrap transition-[background-color,box-shadow,color] duration-300 ease-apple disabled:pointer-events-none disabled:opacity-40';

    $variants = [
        'primary' => 'bg-relais-500 text-white shadow-[0_8px_24px_-10px_var(--color-relais-500)] hover:bg-relais-600',
        'dark' => 'bg-ink text-canvas hover:bg-ink-soft',
        'secondary' => 'bg-ink/[0.06] text-ink hover:bg-ink/10',
        'ghost' => 'text-ink-soft hover:bg-ink/[0.05] hover:text-ink',
        'link' => 'text-relais-600 hover:text-relais-700 px-0!',
        'danger' => 'bg-status-rejected text-white hover:brightness-110',
        'success' => 'bg-status-validated text-white hover:brightness-110',
    ];

    $sizes = [
        'sm' => 'h-8 px-3.5 text-[0.8125rem]',
        'md' => 'h-10 px-5 text-[0.9375rem]',
        'lg' => 'h-12 px-7 text-[1.0625rem]',
    ];

    $classes = implode(' ', [$base, $variants[$variant], $sizes[$size]]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-[1.1em]" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" class="size-[1.1em] transition-transform duration-500 ease-out-expo group-hover/btn:translate-x-0.5" />@endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-[1.1em]" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" class="size-[1.1em] transition-transform duration-500 ease-out-expo group-hover/btn:translate-x-0.5" />@endif
    </button>
@endif
