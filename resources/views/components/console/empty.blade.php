@props(['icon' => 'inbox', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-14 text-center']) }}>
    <span class="relative grid size-14 place-items-center rounded-2xl bg-ink/[0.04] text-mute ring-1 ring-line">
        <x-ui.icon :name="$icon" class="size-6" stroke="1.5" />
    </span>
    <p class="mt-5 text-[0.9375rem] font-semibold tracking-tight">{{ $title }}</p>
    @if ($text)
        <p class="mt-1.5 max-w-sm text-[0.8125rem] leading-relaxed text-mute">{{ $text }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
