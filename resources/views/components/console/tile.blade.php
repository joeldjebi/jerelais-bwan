{{-- Tuile d'icône façon Réglages iOS : carré arrondi de couleur système, pictogramme blanc. --}}
@props(['icon', 'tint' => 'orange', 'size' => 'md'])

@php
    $colors = [
        'orange' => 'bg-tile-orange', 'blue' => 'bg-tile-blue', 'green' => 'bg-tile-green', 'purple' => 'bg-tile-purple',
        'gray' => 'bg-tile-gray', 'pink' => 'bg-tile-pink', 'teal' => 'bg-tile-teal', 'brand' => 'bg-relais-500',
    ];
    $sizes = ['sm' => 'size-6 rounded-[0.4rem] [&_svg]:size-3.5', 'md' => 'size-7 rounded-[0.5rem] [&_svg]:size-4', 'lg' => 'size-10 rounded-xl [&_svg]:size-5'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center text-white shadow-[inset_0_0_0_0.5px_rgb(0_0_0/0.1)] '.$colors[$tint].' '.$sizes[$size]]) }}>
    <x-ui.icon :name="$icon" stroke="2" />
</span>
