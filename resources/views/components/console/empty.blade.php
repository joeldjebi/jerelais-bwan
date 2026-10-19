@props(['icon' => 'inbox', 'title', 'text' => null, 'tint' => 'gray'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-14 text-center']) }}>
    <x-console.tile :icon="$icon" :tint="$tint" size="lg" class="rounded-2xl! size-14! [&_svg]:size-7!" />
    <p class="mt-5 text-[1.0625rem] font-semibold tracking-[-0.01em]">{{ $title }}</p>
    @if ($text)
        <p class="mt-1.5 max-w-sm text-[0.875rem] leading-relaxed text-mute">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
