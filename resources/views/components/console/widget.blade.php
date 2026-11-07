{{--
    Widget façon iOS / macOS pour la grille bento de l'accueil.
    tone : light (blanc), orange, ink (noir), blue, green — couleurs pleines, jamais de dégradé.
--}}
@props(['tone' => 'light', 'href' => null])

@php
    $tones = [
        'light' => 'bg-surface text-ink shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)] dark:shadow-[0_0_0_0.5px_rgb(255_255_255/0.08)]',
        'orange' => 'bg-relais-500 text-white shadow-[0_18px_40px_-18px_rgb(242_140_40/0.7)]',
        'ink' => 'bg-[#1d1d1f] text-white shadow-[0_18px_40px_-20px_rgb(0_0_0/0.6)] dark:bg-[#2c2c2e]',
        'blue' => 'bg-tile-blue text-white shadow-[0_18px_40px_-18px_rgb(10_132_255/0.6)]',
        'green' => 'bg-tile-green text-white shadow-[0_18px_40px_-18px_rgb(48_209_88/0.6)]',
        'navy' => 'bg-navy-900 text-white shadow-[0_18px_40px_-20px_rgb(19_40_74/0.7)]',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif data-widget
    {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-[1.6rem] p-5 transition-transform duration-500 ease-spring will-change-transform hover:scale-[1.015] active:scale-[0.985] '.$tones[$tone]]) }}>
    {{ $slot }}
</{{ $tag }}>
