@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

<div>
    <label for="{{ $name }}" class="block text-[0.8125rem] font-medium text-onyx">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->merge(['class' => 'mt-1.5 block h-12 w-full rounded-xl border-0 bg-mist px-4 text-[0.9375rem] text-onyx ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 placeholder:text-graphite/70 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @if ($hint)
        <p class="mt-1.5 text-xs text-graphite">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p>
    @enderror
</div>
