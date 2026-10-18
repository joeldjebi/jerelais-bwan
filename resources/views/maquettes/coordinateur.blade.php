{{-- Maquette S1 du back-office coordonnateur : file de validation (COO-10 à COO-16). Données fictives. --}}
@php
    $queue = [
        ['Inondation rue principale après l’orage', 'Niangon', 'video', 'urgent', '4 min', true],
        ['Route effondrée près du lycée', 'Sideci', 'photo', 'urgent', '12 min', false],
        ['Coupure d’eau depuis hier', 'Toits-Rouges', 'mic', 'important', '38 min', false],
        ['Nettoyage citoyen du marché', 'Selmer', 'photo', 'normal', '1 h 20', false],
        ['Match de quartier', 'Andokoi', 'video', 'normal', '2 h 05', false],
    ];
@endphp

<x-layouts.console title="À valider" section="coordinateur" active="queue" scope="Coordination · Yopougon" user-name="Aïcha Koné" user-role="Coordonnatrice titulaire" :queue-count="7">
    <div class="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[22rem_1fr]">
        <section class="card overflow-hidden" data-reveal>
            <header class="border-b border-line px-5 py-4">
                <h1 class="text-base font-semibold">File de validation</h1>
                <p class="text-xs text-mute">Triée par urgence puis ancienneté · maquette</p>
            </header>
            <ul class="divide-y divide-line">
                @foreach ($queue as $index => [$title, $place, $media, $urgency, $age, $selected])
                    <li @class([
                        'relative flex gap-3 px-5 py-4 transition-colors animate-feed-in',
                        'bg-relais-500/[0.06]' => $selected,
                        'hover:bg-ink/[0.025]' => ! $selected,
                    ]) style="animation-delay: {{ $index * 80 }}ms">
                        @if ($selected)<span class="absolute inset-y-3 left-0 w-1 rounded-r-full bg-relais-500"></span>@endif
                        <x-ui.icon :name="$media" class="mt-0.5 size-5 text-ink-soft" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $title }}</p>
                            <p class="mt-1 flex items-center gap-2 text-xs text-mute">
                                {{ $place }} · {{ $age }}
                                @if ($urgency === 'urgent')
                                    <span class="rounded-full bg-status-rejected/10 px-1.5 py-px font-semibold text-status-rejected">Urgent</span>
                                @elseif ($urgency === 'important')
                                    <span class="rounded-full bg-relais-500/10 px-1.5 py-px font-semibold text-relais-600">Important</span>
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="flex flex-col gap-6" data-reveal style="--reveal-delay: 120ms">
            <div class="card overflow-hidden">
                <div class="relative grid aspect-video place-items-center bg-black text-white">
                    <button type="button" class="press relative grid size-16 place-items-center rounded-full bg-white/15 backdrop-blur-xl ring-1 ring-white/25" aria-label="Lire la vidéo">
                        <svg viewBox="0 0 24 24" class="ml-1 size-7 fill-white" aria-hidden="true"><path d="M8 5.5v13l10.5-6.5z"/></svg>
                    </button>
                    <div class="absolute inset-x-5 bottom-4 flex items-center gap-3 text-xs text-white/80">
                        <span class="tabular-nums">0:12</span>
                        <span class="h-1 flex-1 overflow-hidden rounded-full bg-white/20"><span class="block h-full w-1/4 rounded-full bg-relais-400"></span></span>
                        <span class="tabular-nums">0:48</span>
                        <span class="rounded-md bg-white/15 px-1.5 py-0.5">1×</span>
                    </div>
                    <span class="absolute top-4 left-4"><x-ui.status status="review" /></span>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                    <div>
                        <h2 class="text-xl font-semibold">Inondation rue principale après l’orage</h2>
                        <p class="mt-1 text-sm text-mute">Météo et environnement · par le Relais n° CI-YOP-0142 · crédité</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-ink/5 px-3 py-1.5 text-xs font-medium text-ink-soft">
                        <x-ui.icon name="clock" class="size-3.5" />Verrouillé pour vous · 9:12
                    </span>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <h3 class="text-sm font-semibold">Vérification</h3>
                    <dl class="mt-4 divide-y divide-line text-sm">
                        @foreach ([
                            ['Lieu GPS / déclaré', 'Écart 120 m', 'ok'],
                            ['Prise de vue / envoi', '19:41 / 19:43', 'ok'],
                            ['Origine du média', 'Capturé en direct', 'ok'],
                            ['Doublon', 'Aucun média proche', 'ok'],
                            ['Fiabilité du Relais', '92 % (48 relais)', 'ok'],
                        ] as [$label, $value, $state])
                            <div class="flex items-center justify-between py-2.5">
                                <dt class="text-mute">{{ $label }}</dt>
                                <dd class="flex items-center gap-2 font-medium">{{ $value }}<x-ui.icon name="check" class="size-4 text-status-validated" stroke="2.25" /></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <div class="card p-6">
                    <h3 class="text-sm font-semibold">Protection des personnes</h3>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        <li class="flex justify-between"><span class="text-mute">Mineurs identifiables</span><span class="font-medium">Non</span></li>
                        <li class="flex justify-between"><span class="text-mute">Accord des personnes filmées</span><span class="font-medium">Oui</span></li>
                        <li class="flex justify-between"><span class="text-mute">Crédit à l’antenne</span><span class="font-medium">Nom du Relais</span></li>
                    </ul>
                    <p class="mt-5 text-sm font-medium">Floutage nécessaire</p>
                    <div class="mt-2 grid grid-cols-2 gap-1 rounded-full bg-ink/5 p-1 text-sm font-medium">
                        <span class="rounded-full py-1.5 text-center text-mute">Oui</span>
                        <span class="rounded-full bg-surface py-1.5 text-center shadow-sm">Non</span>
                    </div>
                </div>
            </div>

            <div class="glass sticky bottom-4 flex flex-wrap items-center justify-end gap-2 rounded-(--radius-card) p-3 shadow-(--shadow-lift) ring-1 ring-line">
                <x-ui.button variant="ghost" icon="flag">Signaler sensible</x-ui.button>
                <x-ui.button variant="secondary">Demander un complément</x-ui.button>
                <x-ui.button variant="danger" icon="x">Rejeter</x-ui.button>
                <x-ui.button variant="success" icon="check">Valider</x-ui.button>
            </div>
        </section>
    </div>
</x-layouts.console>
