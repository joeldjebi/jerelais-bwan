{{-- Ligne d'un relais dans une liste de la console : vignette, titre, badges, lieu, Relais, statut ou délai. --}}
@props(['relais', 'sla' => false])

@php
    $urgencyTone = ['urgent' => 'danger', 'important' => 'warning', 'normal' => 'neutral'];
    $stateTone = ['received' => 'info', 'in_review' => 'accent', 'complement_requested' => 'warning', 'sensitive' => 'warning', 'validated' => 'success', 'selected' => 'success', 'broadcast' => 'success', 'rejected' => 'danger', 'archived' => 'neutral'];
    $kindIcon = ['photo' => 'photo', 'video' => 'video', 'audio' => 'mic'];
    $media = $relais->medias->first();
    $thumb = $media?->variants->firstWhere('kind', 'thumbnail');
    $overdue = $relais->isOverdue();
    $locked = auth()->user() !== null && $relais->isLockedFor(auth()->user());
@endphp

<li wire:key="relais-{{ $relais->uuid }}" class="flex items-center">
    <a href="{{ route('console.validation.show', $relais) }}" wire:navigate class="group flex min-w-0 flex-1 items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
        <span class="relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-[0.8rem] bg-black/[0.05] dark:bg-white/[0.08]">
            @if ($thumb)
                <img src="{{ route('console.medias.show', [$media, 'thumbnail']) }}" alt="" class="size-full object-cover" loading="lazy">
            @else
                <x-ui.icon :name="$kindIcon[$media?->kind->value ?? 'photo']" class="size-5 text-mute" />
            @endif
            @if ($relais->medias->count() > 1)
                <span class="absolute right-1 bottom-1 rounded-full bg-black/60 px-1.5 text-[0.625rem] font-semibold text-white">{{ $relais->medias->count() }}</span>
            @endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
                <span class="truncate text-[0.9375rem] font-semibold">{{ $relais->title ?: __('Sans titre') }}</span>
                @if ($relais->urgency->value !== 'normal')<x-console.badge :tone="$urgencyTone[$relais->urgency->value]">{{ $relais->urgency->label() }}</x-console.badge>@endif
                @if ($relais->has_minors)<x-console.badge tone="warning">{{ __('Mineurs') }}</x-console.badge>@endif
            </span>
            <span class="block truncate text-[0.8125rem] text-mute">
                {{ collect([$relais->category?->name, $relais->commune?->name, $relais->relaisProfile?->display_name])->filter()->join(' · ') }}
            </span>
        </span>
        @if ($locked)
            <span class="hidden items-center gap-1.5 text-[0.75rem] text-mute md:inline-flex"><x-ui.icon name="shield" class="size-3.5" />{{ $relais->lockedBy?->name }}</span>
        @endif
        @if ($sla && $relais->sla_due_at)
            <span @class(['hidden w-28 text-right text-[0.8125rem] tabular-nums sm:block', 'font-semibold text-status-rejected' => $overdue, 'text-mute' => ! $overdue])>
                {{ $overdue ? __('Retard :d', ['d' => $relais->sla_due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) : __('Reste :d', ['d' => $relais->sla_due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
            </span>
        @else
            <x-console.badge :tone="$stateTone[$relais->state->getValue()] ?? 'neutral'" class="hidden sm:inline-flex">{{ $relais->state->label() }}</x-console.badge>
            <span class="hidden w-20 text-right text-[0.8125rem] text-mute tabular-nums md:block">{{ $relais->received_at?->diffForHumans(short: true) }}</span>
        @endif
        <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </a>
    {{ $slot }}
</li>
