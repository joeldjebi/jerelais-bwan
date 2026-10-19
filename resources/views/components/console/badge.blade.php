@props(['tone' => 'neutral', 'dot' => false])

@php
    $tones = [
        'neutral' => 'bg-ink/[0.06] text-ink-soft',
        'success' => 'bg-status-validated/12 text-status-validated',
        'warning' => 'bg-status-pending/15 text-[#b77400] dark:text-status-pending',
        'danger' => 'bg-status-rejected/12 text-status-rejected',
        'accent' => 'bg-relais-500/12 text-relais-600',
        'info' => 'bg-status-broadcast/12 text-status-broadcast',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap '.$tones[$tone]]) }}>
    @if ($dot)<span class="size-1.5 rounded-full bg-current"></span>@endif
    {{ $slot }}
</span>
