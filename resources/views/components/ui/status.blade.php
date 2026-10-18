{{-- Pastille de statut d'un relais (§8.1). --}}
@props(['status', 'pulse' => false])

@php
    $map = [
        'pending' => ['En attente', 'text-status-pending bg-status-pending/12'],
        'review' => ['En vérification', 'text-status-review bg-status-review/12'],
        'complement' => ['Complément demandé', 'text-relais-600 bg-relais-500/12'],
        'validated' => ['Validé', 'text-status-validated bg-status-validated/12'],
        'rejected' => ['Rejeté', 'text-status-rejected bg-status-rejected/12'],
        'sensitive' => ['Sensible', 'text-status-sensitive bg-status-sensitive/12'],
        'selected' => ['Sélectionné', 'text-navy-600 bg-navy-400/15 dark:text-navy-200'],
        'broadcast' => ['Diffusé', 'text-status-broadcast bg-status-broadcast/12'],
    ];

    [$label, $classes] = $map[$status] ?? [$status, 'text-mute bg-ink/5'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-(--radius-pill) px-2.5 py-1 text-xs font-semibold {$classes}"]) }}>
    <span @class(['size-1.5 rounded-full bg-current', 'animate-pulse-dot' => $pulse])></span>
    {{ $label }}
</span>
