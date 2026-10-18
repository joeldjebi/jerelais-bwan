{{-- Aperçu animé de la console REFLET TV : les relais arrivent au fil de l'eau (données fictives de démonstration). --}}
@php
    $initialFeed = [
        [__('Coupure d’électricité depuis 6 h'), 'Abobo', 'photo', 'review'],
        [__('Rentrée à l’université'), 'Cocody', 'video', 'validated'],
        [__('Prix de la tomate au grand marché'), 'Bouaké', 'mic', 'selected'],
        [__('Nouveau centre de santé'), 'Korhogo', 'video', 'broadcast'],
    ];

    $feedPool = [
        ['title' => __('Embouteillage monstre sur le pont'), 'place' => 'Plateau', 'icon' => 'video'],
        ['title' => __('Marché inondé après l’averse'), 'place' => 'Adjamé', 'icon' => 'photo'],
        ['title' => __('Coupure d’eau depuis hier soir'), 'place' => 'Koumassi', 'icon' => 'mic'],
        ['title' => __('Tournoi de quartier en finale'), 'place' => 'Marcory', 'icon' => 'video'],
        ['title' => __('Route dégradée vers le lycée'), 'place' => 'Daloa', 'icon' => 'photo'],
        ['title' => __('Campagne de vaccination'), 'place' => 'San-Pédro', 'icon' => 'video'],
        ['title' => __('Nouvel éclairage public'), 'place' => 'Port-Bouët', 'icon' => 'photo'],
    ];
@endphp

<div class="overflow-hidden rounded-2xl bg-white shadow-[0_30px_80px_-30px_rgb(0_0_0/0.35)] ring-1 ring-black/5 sm:rounded-[1.25rem]" data-console-window>
    <div class="flex h-10 items-center gap-2 border-b border-black/5 bg-mist px-4">
        <span class="size-3 rounded-full bg-[#ff5f57]"></span>
        <span class="size-3 rounded-full bg-[#febc2e]"></span>
        <span class="size-3 rounded-full bg-[#28c840]"></span>
        <span class="mx-auto rounded-md bg-white px-10 py-1 text-[0.6875rem] text-graphite ring-1 ring-black/5">console.jerelais.ci</span>
    </div>

    <div class="grid min-h-[22rem] grid-cols-1 md:grid-cols-[1fr_15rem]">
        <div class="p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold">{{ __('Relais reçus') }}</p>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-status-validated/10 px-2.5 py-1 text-[0.6875rem] font-semibold text-status-validated">
                    <span class="size-1.5 animate-blink rounded-full bg-current"></span>{{ __('Temps réel') }}
                </span>
            </div>
            <ul class="mt-4 space-y-2" data-live-feed>
                @foreach ($initialFeed as [$title, $place, $icon, $status])
                    <li class="flex items-center gap-3 rounded-xl bg-mist/70 px-3 py-2.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-onyx ring-1 ring-black/5"><x-ui.icon :name="$icon" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.8125rem] font-medium">{{ $title }}</p>
                            <p class="text-[0.6875rem] text-graphite">{{ $place }}</p>
                        </div>
                        <x-ui.status :status="$status" />
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="hidden border-l border-black/5 bg-mist/50 p-5 md:block">
            <p class="text-[0.6875rem] font-semibold tracking-wide text-graphite uppercase">{{ __('Alertes') }}</p>
            <div class="mt-3 space-y-2">
                <div class="rounded-xl bg-white p-3 ring-1 ring-black/5">
                    <p class="flex items-center gap-1.5 text-[0.75rem] font-semibold"><x-ui.icon name="bolt" class="size-3.5 text-status-rejected" />{{ __('Afflux à Yopougon') }}</p>
                    <p class="mt-1 text-[0.6875rem] text-graphite">{{ __('Plusieurs relais en quelques minutes : possible événement.') }}</p>
                </div>
                <div class="rounded-xl bg-white p-3 ring-1 ring-black/5">
                    <p class="flex items-center gap-1.5 text-[0.75rem] font-semibold"><x-ui.icon name="clock" class="size-3.5 text-relais-600" />{{ __('Validation en retard') }}</p>
                    <p class="mt-1 text-[0.6875rem] text-graphite">{{ __('Escaladé automatiquement au suppléant.') }}</p>
                </div>
            </div>
            <p class="mt-5 text-[0.6875rem] font-semibold tracking-wide text-graphite uppercase">{{ __('Diffusion') }}</p>
            <div class="mt-3 rounded-xl bg-white p-3 ring-1 ring-black/5">
                <x-ui.status status="broadcast" />
                <p class="mt-2 text-[0.75rem] font-semibold">{{ __('Pluies diluviennes à Yopougon') }}</p>
                <p class="text-[0.625rem] text-graphite">{{ __('Crédit : Relais de Niangon') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Modèles injectés par le JS dans le flux --}}
<template data-feed-template>
    <li class="flex items-center gap-3 rounded-xl bg-mist/70 px-3 py-2.5">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-onyx ring-1 ring-black/5" data-feed-icon></span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-[0.8125rem] font-medium" data-feed-title></p>
            <p class="text-[0.6875rem] text-graphite" data-feed-place></p>
        </div>
        <span data-feed-status><x-ui.status status="pending" :pulse="true" /></span>
    </li>
</template>
<template data-status-templates>
    <x-ui.status status="review" data-status="review" />
    <x-ui.status status="validated" data-status="validated" />
</template>
<template data-feed-icons>
    <x-ui.icon name="video" class="size-4" data-icon="video" />
    <x-ui.icon name="photo" class="size-4" data-icon="photo" />
    <x-ui.icon name="mic" class="size-4" data-icon="mic" />
</template>
<script type="application/json" data-feed-pool>@json($feedPool)</script>
