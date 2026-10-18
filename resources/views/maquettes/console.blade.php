{{-- Maquette S1 de la console REFLET TV (ADM-01 à ADM-05). Données fictives de démonstration. --}}
@php
    $feed = [
        ['Inondation rue principale après l’orage', 'Yopougon · Niangon', 'video', 'urgent', 'pending', 'à l’instant'],
        ['Coupure d’électricité depuis 6 h', 'Abobo · PK18', 'photo', 'important', 'review', 'il y a 2 min'],
        ['Rentrée à l’université Félix Houphouët-Boigny', 'Cocody', 'video', 'normal', 'validated', 'il y a 6 min'],
        ['Hausse du prix de la tomate au marché', 'Bouaké · Grand marché', 'mic', 'normal', 'selected', 'il y a 11 min'],
        ['Accident sur le pont, circulation bloquée', 'Plateau', 'photo', 'urgent', 'sensitive', 'il y a 14 min'],
        ['Inauguration du centre de santé', 'Korhogo', 'video', 'normal', 'broadcast', 'il y a 32 min'],
    ];
    $communes = [['Yopougon', 32, 68, 9], ['Abobo', 52, 40, 6], ['Cocody', 64, 58, 4], ['Plateau', 50, 66, 3], ['Bouaké', 46, 18, 5], ['Korhogo', 40, 6, 2]];
@endphp

<x-layouts.console title="Direct" active="live" user-name="Aïcha Koné" user-role="Rédactrice en chef">
    <div class="mx-auto max-w-7xl">
        <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
            <div>
                <p class="eyebrow">{{ __('Direct') }}</p>
                <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Bonsoir, Aïcha.</h1>
                <p class="mt-1 text-mute">Tout ce qui remonte du réseau, en temps réel. <span class="text-xs">(maquette — données fictives)</span></p>
            </div>
            <div class="flex gap-2">
                <x-ui.button variant="secondary" icon="layers">Vue salle de rédaction</x-ui.button>
                <x-ui.button icon="bolt">Lancer un appel à relais</x-ui.button>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4" data-stagger="70">
            <x-ui.stat label="Relais reçus aujourd’hui" value="248" icon="inbox" trend="Démo" data-reveal />
            <x-ui.stat label="En attente de validation" value="37" icon="clock" tone="accent" trend="Démo" data-reveal />
            <x-ui.stat label="Urgents en retard" value="3" icon="bolt" tone="alert" trend="Délai cible 30 min dépassé" data-reveal />
            <x-ui.stat label="Relais actifs (30 j)" value="1284" icon="users" trend="Démo" data-reveal />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <section class="card overflow-hidden" data-reveal>
                <header class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h2 class="text-base font-semibold">Flux en direct</h2>
                    <div class="flex gap-1 rounded-full bg-ink/5 p-1 text-xs font-medium">
                        <span class="rounded-full bg-surface px-3 py-1 shadow-sm">Tous</span>
                        <span class="px-3 py-1 text-mute">Urgents</span>
                        <span class="px-3 py-1 text-mute">À exploiter</span>
                    </div>
                </header>
                <ul class="divide-y divide-line">
                    @foreach ($feed as $index => [$title, $place, $media, $urgency, $status, $when])
                        <li class="group flex items-center gap-4 px-6 py-4 transition-colors hover:bg-ink/[0.025] animate-feed-in" style="animation-delay: {{ $index * 90 }}ms">
                            <span @class([
                                'grid size-11 shrink-0 place-items-center rounded-2xl',
                                'bg-status-rejected/10 text-status-rejected' => $urgency === 'urgent',
                                'bg-relais-500/10 text-relais-600' => $urgency === 'important',
                                'bg-ink/5 text-ink-soft' => $urgency === 'normal',
                            ])>
                                <x-ui.icon :name="$media" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ $title }}</p>
                                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-mute">
                                    <x-ui.icon name="map-pin" class="size-3.5" />{{ $place }} · {{ $when }}
                                    @if ($urgency === 'urgent')<span class="font-semibold text-status-rejected">· Urgent</span>@endif
                                </p>
                            </div>
                            <x-ui.status :status="$status" :pulse="$status === 'pending'" />
                            <x-ui.icon name="arrow-up-right" class="size-4 text-mute opacity-0 transition-opacity group-hover:opacity-100" />
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="flex flex-col gap-6">
                <section class="card overflow-hidden" data-reveal>
                    <header class="flex items-center justify-between px-6 pt-5">
                        <h2 class="text-base font-semibold">Carte du réseau</h2>
                        <span class="text-xs text-mute">Leaflet + OpenStreetMap à l’étape 4</span>
                    </header>
                    <div class="relative m-4 aspect-[4/3] overflow-hidden rounded-2xl bg-mist dark:bg-onyx">
                        @foreach ($communes as [$name, $x, $y, $count])
                            <div class="group absolute -translate-x-1/2 -translate-y-1/2" style="left: {{ $x }}%; top: {{ $y + 12 }}%">
                                <span class="absolute inset-0 animate-ping rounded-full bg-relais-500/30"></span>
                                <span class="relative grid place-items-center rounded-full bg-relais-500 font-semibold text-white shadow-lg ring-4 ring-relais-500/20" style="width: {{ 18 + $count * 3 }}px; height: {{ 18 + $count * 3 }}px; font-size: 10px">{{ $count }}</span>
                                <span class="glass absolute top-full left-1/2 mt-1.5 -translate-x-1/2 rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap opacity-0 ring-1 ring-line transition-opacity group-hover:opacity-100">{{ $name }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="card p-6" data-reveal>
                    <h2 class="text-base font-semibold">Alertes</h2>
                    <ul class="mt-4 space-y-3">
                        <li class="flex gap-3 rounded-2xl bg-status-rejected/8 p-3.5">
                            <x-ui.icon name="bolt" class="mt-0.5 size-4 text-status-rejected" />
                            <div class="text-sm"><p class="font-medium">Afflux inhabituel à Yopougon</p><p class="text-mute">9 relais en 15 minutes — possible événement.</p></div>
                        </li>
                        <li class="flex gap-3 rounded-2xl bg-relais-500/8 p-3.5">
                            <x-ui.icon name="clock" class="mt-0.5 size-4 text-relais-600" />
                            <div class="text-sm"><p class="font-medium">3 relais urgents en retard</p><p class="text-mute">Escaladés au suppléant de Plateau.</p></div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</x-layouts.console>
