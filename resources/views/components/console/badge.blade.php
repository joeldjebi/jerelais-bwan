@props(['tone' => 'neutral', 'dot' => false])

@php
    $tones = [
        'neutral' => 'bg-black/[0.05] text-ink-soft dark:bg-white/[0.08]',
        'success' => 'bg-status-validated/14 text-[#248a3d] dark:text-status-validated',
        'warning' => 'bg-status-pending/18 text-[#a05a00] dark:text-status-pending',
        'danger' => 'bg-status-rejected/12 text-[#d70015] dark:text-status-rejected',
        'accent' => 'bg-relais-500/14 text-relais-700 dark:text-relais-400',
        'info' => 'bg-status-broadcast/12 text-[#0060df] dark:text-status-broadcast',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[0.75rem] font-semibold whitespace-nowrap '.$tones[$tone]]) }}>
    @if ($dot)<span class="size-1.5 rounded-full bg-current"></span>@endif
    {{ $slot }}
</span>
