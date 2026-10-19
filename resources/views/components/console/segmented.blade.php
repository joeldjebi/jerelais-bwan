{{-- Contrôle segmenté façon iOS, lié à une propriété Livewire (wire:click="$set(...)"). --}}
@props(['options', 'current', 'property'])

<div {{ $attributes->merge(['class' => 'inline-grid auto-cols-fr grid-flow-col gap-0.5 rounded-[0.65rem] bg-black/[0.055] p-0.5 text-[0.8125rem] font-medium dark:bg-white/[0.08]']) }} role="tablist">
    @foreach ($options as $value => $label)
        <button type="button" wire:click="$set('{{ $property }}', '{{ $value }}')" role="tab" aria-selected="{{ (string) $current === (string) $value ? 'true' : 'false' }}"
            @class([
                'rounded-[0.5rem] px-3 py-1.5 whitespace-nowrap transition-all duration-300 ease-spring',
                'bg-white text-ink shadow-[0_1px_4px_rgb(0_0_0/0.15)] dark:bg-[#636366]' => (string) $current === (string) $value,
                'text-ink-soft hover:text-ink' => (string) $current !== (string) $value,
            ])>{{ $label }}</button>
    @endforeach
</div>
