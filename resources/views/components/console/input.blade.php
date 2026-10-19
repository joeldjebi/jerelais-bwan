@props(['label', 'name', 'type' => 'text', 'hint' => null, 'suffix' => null])

<div>
    <label for="{{ $name }}" class="block text-[0.8125rem] font-medium">{{ $label }}</label>
    <div class="relative mt-1.5">
        <input
            id="{{ $name }}"
            type="{{ $type }}"
            {{ $attributes->merge(['class' => 'block h-11 w-full rounded-[0.65rem] border-0 bg-black/[0.055] dark:bg-white/[0.08] px-3.5 text-[0.9375rem] transition-[box-shadow,background-color] duration-300 placeholder:text-mute focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none'.($suffix ? ' pr-20!' : '')]) }}
            @error($name) aria-invalid="true" @enderror
        >
        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-[0.8125rem] text-mute">{{ $suffix }}</span>
        @endif
    </div>
    @if ($hint)
        <p class="mt-1.5 text-xs text-mute">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p>
    @enderror
</div>
