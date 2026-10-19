{{-- Indicateur compact (widget 1×1) : tuile d'icône, grand chiffre qui défile. --}}
@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'default', 'href' => null, 'count' => null, 'suffix' => null, 'tint' => null])

@php
    $tint ??= ['default' => 'gray', 'accent' => 'orange', 'success' => 'green', 'alert' => 'pink'][$tone] ?? 'gray';
@endphp

<x-console.widget :href="$href" {{ $attributes }}>
    <div class="flex items-center gap-2.5">
        @if ($icon)
            <x-console.tile :icon="$icon" :tint="$tint" size="sm" />
        @endif
        <p class="truncate text-[0.8125rem] font-semibold text-mute">{{ $label }}</p>
    </div>
    <p class="mt-6 text-[2.5rem] leading-none font-bold tracking-[-0.03em] tabular-nums">
        @if ($count !== null)<span data-count="{{ $count }}">{{ number_format((float) $count, 0, ',', ' ') }}</span><span class="text-[1.5rem] font-semibold text-mute">{{ $suffix }}</span>@else{{ $value }}@endif
    </p>
    @if ($hint)
        <p class="mt-2 text-[0.8125rem] leading-snug text-mute">{{ $hint }}</p>
    @endif
    @if ($href)
        <svg class="absolute top-5 right-5 size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    @endif
</x-console.widget>
