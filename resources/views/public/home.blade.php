<x-layouts.public>
    {{-- HERO --}}
    <section class="relative isolate overflow-hidden pt-32 pb-24 sm:pt-40 sm:pb-32">
        <div class="parallax-slow pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute top-[-12%] left-1/2 h-[42rem] w-[42rem] -translate-x-[70%] rounded-full bg-relais-400/25 blur-[120px] dark:bg-relais-500/20"></div>
            <div class="absolute top-[8%] left-1/2 h-[36rem] w-[36rem] -translate-x-[5%] rounded-full bg-navy-400/25 blur-[120px] dark:bg-navy-600/35"></div>
        </div>

        <div class="mx-auto grid max-w-6xl items-center gap-16 px-5 sm:px-8 lg:grid-cols-[1.15fr_1fr]">
            <div data-stagger="90">
                <p class="eyebrow" data-reveal>{{ __('REFLET TV · Observateurs citoyens') }}</p>
                <h1 class="mt-5 text-[2.75rem] leading-[1.02] font-semibold sm:text-[4.25rem]" data-reveal>
                    {{ __('Ce que vous voyez,') }}
                    <span class="text-gradient block">{{ __('toute la Côte d’Ivoire le verra.') }}</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-soft sm:text-xl" data-reveal>
                    {{ __('Devenez Relais : filmez, photographiez ou enregistrez ce qui se passe près de chez vous. Votre coordonnateur vérifie, la rédaction de REFLET TV le porte à l’antenne.') }}
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3" data-reveal>
                    <x-ui.button href="#devenir-relais" size="lg" icon-trailing="arrow-right">{{ __('Devenir Relais') }}</x-ui.button>
                    <x-ui.button href="#principe" size="lg" variant="secondary">{{ __('Comment ça marche') }}</x-ui.button>
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-line pt-8" data-reveal>
                    <div>
                        <dt class="text-xs text-mute">{{ __('pour envoyer') }}</dt>
                        <dd class="mt-1 font-display text-2xl font-semibold tracking-tight">&lt; 2 min</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-mute">{{ __('appuis maximum') }}</dt>
                        <dd class="mt-1 font-display text-2xl font-semibold tracking-tight">3</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-mute">{{ __('vérification urgente') }}</dt>
                        <dd class="mt-1 font-display text-2xl font-semibold tracking-tight">30 min</dd>
                    </div>
                </dl>
            </div>

            {{-- Téléphone : écran « Relayer » --}}
            <div class="relative mx-auto w-full max-w-[20rem]" data-reveal style="--reveal-delay: 250ms">
                <div class="relative rounded-[3rem] bg-[#1d1d1f] p-2.5 shadow-(--shadow-lift) ring-1 ring-white/10">
                    <div class="relative aspect-[9/19] overflow-hidden rounded-[2.4rem] bg-linear-to-b from-navy-800 via-navy-900 to-navy-950">
                        <div class="absolute top-2.5 left-1/2 h-6 w-24 -translate-x-1/2 rounded-full bg-black"></div>
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_35%,rgba(242,140,40,0.35),transparent_55%)]"></div>
                        <div class="absolute inset-x-0 top-14 px-5 text-white">
                            <div class="flex items-center justify-between text-[0.6875rem] text-white/70">
                                <span class="inline-flex items-center gap-1"><x-ui.icon name="map-pin" class="size-3.5" />Yopougon · Niangon</span>
                                <span class="inline-flex items-center gap-1.5"><span class="size-1.5 animate-pulse-dot rounded-full bg-status-rejected text-status-rejected"></span>REC 00:42</span>
                            </div>
                        </div>
                        <div class="absolute inset-x-5 top-1/2 -translate-y-1/2 rounded-2xl border border-white/15 p-4 text-white/80">
                            <div class="grid aspect-video place-items-center rounded-xl bg-white/5">
                                <x-ui.icon name="video" class="size-9 text-white/60" stroke="1.25" />
                            </div>
                        </div>
                        <div class="absolute inset-x-0 bottom-8 flex flex-col items-center gap-4">
                            <div class="flex gap-5 text-[0.6875rem] font-medium tracking-wide text-white/50 uppercase">
                                <span>{{ __('Photo') }}</span><span class="text-relais-300">{{ __('Vidéo') }}</span><span>{{ __('Audio') }}</span>
                            </div>
                            <div class="grid size-[4.5rem] place-items-center rounded-full border-4 border-white/90">
                                <div class="size-12 rounded-full bg-relais-500 shadow-[0_0_24px_var(--color-relais-500)]"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card absolute top-36 -left-14 hidden animate-float items-center gap-3 px-4 py-3 sm:flex">
                    <span class="grid size-8 place-items-center rounded-full bg-status-validated/15 text-status-validated"><x-ui.icon name="check" class="size-4" stroke="2.25" /></span>
                    <div>
                        <p class="text-[0.8125rem] font-semibold">{{ __('Relais validé') }}</p>
                        <p class="text-xs text-mute">{{ __('par votre coordonnateur') }}</p>
                    </div>
                </div>

                <div class="card absolute -right-12 bottom-24 hidden animate-float items-center gap-3 px-4 py-3 [animation-delay:-4s] sm:flex">
                    <span class="grid size-8 place-items-center rounded-full bg-status-broadcast/15 text-status-broadcast"><x-ui.icon name="signal" class="size-4" stroke="2" /></span>
                    <div>
                        <p class="text-[0.8125rem] font-semibold">{{ __('Diffusé sur REFLET TV') }}</p>
                        <p class="text-xs text-mute">{{ __('Vous êtes prévenu') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- LIEUX --}}
    <section class="border-y border-line bg-surface/60 py-6" aria-label="{{ __('Là où naît l’information') }}">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-5 text-sm font-medium text-mute sm:px-8" data-stagger="50">
            @foreach ([__('Communes'), __('Quartiers'), __('Villages'), __('Écoles'), __('Universités'), __('Marchés'), __('Gares'), __('Capitales régionales')] as $place)
                <span data-reveal>{{ $place }}</span>
            @endforeach
        </div>
    </section>

    {{-- PRINCIPE --}}
    <section id="principe" class="py-24 sm:py-32">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="max-w-2xl" data-reveal>
                <p class="eyebrow">{{ __('Le principe') }}</p>
                <h2 class="mt-4 text-4xl font-semibold sm:text-5xl">{{ __('Du terrain à l’antenne, en trois temps.') }}</h2>
            </div>

            <div class="mt-14 grid gap-5 md:grid-cols-3" data-stagger="110">
                @foreach ([
                    ['camera', '01', __('Vous relayez'), __('Ouvrez l’application, appuyez sur « Relayer ». Photo, vidéo ou note vocale : la position et l’heure sont ajoutées automatiquement.')],
                    ['shield', '02', __('Votre coordonnateur vérifie'), __('Un coordonnateur de votre commune, qui connaît le terrain, contrôle chaque relais avant qu’il n’arrive à la rédaction.')],
                    ['signal', '03', __('REFLET TV diffuse'), __('La rédaction sélectionne les relais validés et les porte à l’antenne. Vous êtes prévenu dès la diffusion.')],
                ] as [$icon, $step, $title, $text])
                    <article class="card-interactive group p-7" data-reveal>
                        <div class="flex items-center justify-between">
                            <span class="grid size-11 place-items-center rounded-2xl bg-relais-500/10 text-relais-600 transition-transform duration-700 ease-spring group-hover:scale-110 group-hover:-rotate-6">
                                <x-ui.icon :name="$icon" class="size-5" />
                            </span>
                            <span class="font-display text-sm font-semibold text-mute tabular-nums">{{ $step }}</span>
                        </div>
                        <h3 class="mt-8 text-xl font-semibold">{{ $title }}</h3>
                        <p class="mt-3 leading-relaxed text-mute">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- RÉSEAU FAIBLE --}}
    <section class="px-3 sm:px-5">
        <div class="relative isolate mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-navy-950 px-6 py-20 text-white sm:px-14 sm:py-28">
            <div class="pointer-events-none absolute -top-40 right-0 -z-10 h-[30rem] w-[30rem] rounded-full bg-relais-500/30 blur-[120px]" aria-hidden="true"></div>
            <div class="grid gap-14 lg:grid-cols-2 lg:items-center">
                <div data-reveal>
                    <p class="eyebrow text-relais-300!">{{ __('Pensé pour le terrain') }}</p>
                    <h2 class="mt-4 text-4xl font-semibold text-white sm:text-5xl">{{ __('Même avec une connexion faible, rien ne se perd.') }}</h2>
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-white/70">{{ __('L’envoi reprend là où il s’est arrêté. Sans réseau, votre relais attend, chiffré dans le téléphone, et part tout seul au retour de la connexion.') }}</p>
                </div>
                <ul class="grid gap-3 sm:grid-cols-2" data-stagger="90">
                    @foreach ([
                        ['layers', __('Envoi reprenable'), __('Une coupure ne fait pas tout recommencer.')],
                        ['bolt', __('Aperçu prioritaire'), __('Une version légère part d’abord, l’original ensuite.')],
                        ['mic', __('Toutes les langues'), __('Décrivez en note vocale, dans la langue de votre choix.')],
                        ['eye-off', __('Anonymat possible'), __('Choisissez d’être crédité ou de rester anonyme.')],
                    ] as [$icon, $title, $text])
                        <li class="rounded-2xl bg-white/[0.06] p-5 ring-1 ring-white/10 backdrop-blur" data-reveal>
                            <x-ui.icon :name="$icon" class="size-5 text-relais-300" />
                            <p class="mt-4 font-semibold">{{ $title }}</p>
                            <p class="mt-1.5 text-sm leading-relaxed text-white/60">{{ $text }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- CONFIANCE --}}
    <section id="confiance" class="py-24 sm:py-32">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <p class="eyebrow">{{ __('Confiance') }}</p>
                <h2 class="mt-4 text-4xl font-semibold sm:text-5xl">{{ __('Fiable pour la chaîne. Sûr pour vous.') }}</h2>
            </div>
            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" data-stagger="90">
                @foreach ([
                    ['map-pin', __('Vérifié localement'), __('Chaque relais passe par le coordonnateur de la commune où il a été filmé.')],
                    ['shield', __('Données protégées'), __('Pièces d’identité chiffrées, positions jamais publiées.')],
                    ['users', __('Personnes respectées'), __('Mineurs et personnes filmées : consignes de floutage transmises à la rédaction.')],
                    ['flag', __('Une charte claire'), __('Vous transmettez ce que vous avez vu, sans vous mettre en danger.')],
                ] as [$icon, $title, $text])
                    <div class="card p-6" data-reveal>
                        <x-ui.icon :name="$icon" class="size-5 text-relais-600" />
                        <p class="mt-5 font-semibold">{{ $title }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-mute">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- DEVENIR RELAIS --}}
    <section id="devenir-relais" class="pb-24 sm:pb-32">
        <div class="mx-auto grid max-w-6xl gap-14 px-5 sm:px-8 lg:grid-cols-2">
            <div data-reveal>
                <p class="eyebrow">{{ __('Devenir Relais') }}</p>
                <h2 class="mt-4 text-4xl font-semibold sm:text-5xl">{{ __('Rejoignez le réseau de votre commune.') }}</h2>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-ink-soft">{{ __('Inscription avec votre numéro de téléphone, une courte formation, puis la validation de votre coordonnateur. Vous recevez alors votre badge numérique de Relais.') }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <x-ui.button variant="dark" size="lg" disabled>{{ __('Bientôt sur Google Play') }}</x-ui.button>
                    <x-ui.button variant="secondary" size="lg" disabled>{{ __('Bientôt sur l’App Store') }}</x-ui.button>
                </div>
            </div>
            <ol class="relative space-y-3" data-stagger="80">
                @foreach ([
                    __('Créez votre compte avec un code SMS'),
                    __('Complétez votre dossier et votre pièce d’identité'),
                    __('Acceptez la charte du Relais'),
                    __('Suivez la formation et répondez au quiz'),
                    __('Votre coordonnateur valide : vous êtes Relais'),
                ] as $index => $step)
                    <li class="card flex items-center gap-4 p-4 pr-6" data-reveal>
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-canvas tabular-nums">{{ $index + 1 }}</span>
                        <span class="font-medium">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="border-t border-line py-24 sm:py-32">
        <div class="mx-auto max-w-3xl px-5 sm:px-8">
            <h2 class="text-center text-4xl font-semibold sm:text-5xl" data-reveal>{{ __('Questions fréquentes') }}</h2>
            <div class="mt-12 divide-y divide-line" data-stagger="70">
                @foreach ([
                    [__('Faut-il être journaliste pour devenir Relais ?'), __('Non. Un Relais est un observateur citoyen. La charte rappelle d’ailleurs qu’un Relais ne se présente jamais comme journaliste ou salarié de REFLET TV.')],
                    [__('Mon nom apparaîtra-t-il à l’antenne ?'), __('C’est vous qui choisissez, à chaque envoi : être crédité ou rester anonyme.')],
                    [__('Que se passe-t-il si je n’ai pas de réseau ?'), __('Votre relais est préparé et conservé chiffré sur votre téléphone, puis envoyé automatiquement au retour du réseau.')],
                    [__('Qui voit ma pièce d’identité ?'), __('Uniquement le coordonnateur de votre commune et les administrateurs habilités de REFLET TV.')],
                ] as [$question, $answer])
                    <details class="group py-6" data-reveal>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-medium">
                            {{ $question }}
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ink/5 transition-transform duration-500 ease-spring group-open:rotate-45">
                                <x-ui.icon name="x" class="size-3.5 rotate-45" stroke="2" />
                            </span>
                        </summary>
                        <p class="mt-4 leading-relaxed text-mute">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public>
