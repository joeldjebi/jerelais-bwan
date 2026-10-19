@php
    $places = [__('Communes'), __('Quartiers'), __('Villages'), __('Écoles'), __('Universités'), __('Marchés'), __('Gares'), __('Capitales régionales')];
@endphp

<x-layouts.public>
    {{-- 1. HERO : mosaïque de relais en parallaxe --}}
    <section class="relative h-[100svh] min-h-[38rem] overflow-hidden bg-white" data-hero>
        <div class="relative z-20 flex flex-col items-center px-5 pt-24 text-center sm:pt-28" data-hero-copy>
            <p class="inline-flex items-center gap-2 text-[0.9375rem] font-medium text-graphite" data-hero-fade>
                {{ __('REFLET TV présente') }}
            </p>
            <h1 class="mt-3 text-[clamp(3.75rem,11vw,9rem)] leading-[0.92] font-semibold tracking-[-0.045em]" data-hero-title>Je relais.</h1>
            <p class="mt-5 max-w-xl text-[clamp(1.125rem,2vw,1.5rem)] leading-snug font-medium tracking-tight text-onyx/85" data-hero-fade>
                {{ __('Ce que vous voyez près de chez vous peut passer à l’antenne.') }}
            </p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-6" data-hero-fade>
                <a href="{{ route('relais.join') }}" class="press rounded-full bg-relais-500 px-6 py-3 text-[1.0625rem] font-medium text-white transition-colors duration-300 hover:bg-relais-600">{{ __('Devenir Relais') }}</a>
                <a href="#principe" class="group inline-flex items-center gap-1 text-[1.0625rem] text-relais-600 hover:underline">
                    {{ __('Voir comment ça marche') }}
                    <x-ui.icon name="arrow-right" class="size-4 transition-transform duration-500 ease-out-expo group-hover:translate-x-0.5" />
                </a>
            </div>
        </div>

        @include('public.partials.hero-wall')

    </section>

    {{-- 2. MANIFESTE --}}
    <section id="principe" class="bg-white px-5 py-36 sm:py-48">
        <p class="mx-auto max-w-[1024px] text-[clamp(2rem,4.6vw,4rem)] leading-[1.12] font-semibold tracking-[-0.03em]" data-scrub-text>
            {{ __('Partout où l’information naît — dans les communes, les quartiers, les villages, les écoles, les marchés — un Relais est là. Il filme. Son coordonnateur vérifie. REFLET TV diffuse.') }}
        </p>
    </section>

    {{-- 3. RÉCIT ÉPINGLÉ : TROIS TEMPS --}}
    <section class="flex min-h-[100svh] items-center bg-mist px-5 py-20" data-story>
        <div class="mx-auto grid w-full max-w-[1024px] items-center gap-8 md:grid-cols-2 md:gap-16">
            <div>
                <div class="flex gap-2" aria-hidden="true">
                    @foreach ([1, 2, 3] as $bar)
                        <span class="h-1 w-14 overflow-hidden rounded-full bg-black/10"><span class="block h-full origin-left scale-x-0 bg-onyx" data-story-bar></span></span>
                    @endforeach
                </div>
                <div class="mt-6 md:mt-10" data-story-stack>
                    @foreach ([
                        ['01', __('Vous filmez.'), __('Un appui sur « Relayer ». Photo, vidéo ou note vocale : la position, la date et l’heure sont ajoutées automatiquement.')],
                        ['02', __('On vérifie.'), __('Le coordonnateur de votre commune, qui connaît le terrain, contrôle le lieu, la date et l’origine de chaque relais.')],
                        ['03', __('C’est à l’antenne.'), __('La rédaction de REFLET TV sélectionne les relais validés. Vous êtes prévenu au moment de la diffusion.')],
                    ] as [$number, $title, $text])
                        <div class="pb-10 md:pb-0" data-story-step>
                            <p class="font-display text-sm font-semibold text-relais-600 tabular-nums">{{ $number }}</p>
                            <h2 class="mt-2 text-[clamp(2.25rem,6vw,4.75rem)] leading-[1] font-semibold tracking-[-0.04em] md:mt-3">{{ $title }}</h2>
                            <p class="mt-3 max-w-md text-base leading-relaxed text-graphite md:mt-6 md:text-lg">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mx-auto w-[min(46vw,200px)] md:w-[300px]">
                <div class="rounded-[2.4rem] bg-onyx p-2 md:rounded-[3.2rem] md:p-3">
                    <div class="relative aspect-[9/19.5] overflow-hidden rounded-[2rem] md:rounded-[2.5rem]" data-story-stack>
                        {{-- Écran 1 : capture --}}
                        <div class="relative bg-black text-white" data-story-screen>
                            <div class="absolute top-3 left-1/2 h-6 w-24 -translate-x-1/2 rounded-full bg-onyx"></div>
                            <div class="absolute inset-x-5 top-14 flex items-center justify-between text-[0.6875rem] font-medium">
                                <span class="inline-flex items-center gap-1 text-white/70"><x-ui.icon name="map-pin" class="size-3.5" />Niangon</span>
                                <span class="inline-flex items-center gap-1.5"><span class="size-1.5 animate-blink rounded-full bg-[#ff3b30]"></span>REC 00:42</span>
                            </div>
                            <div class="absolute inset-x-6 top-28 bottom-40">
                                <span class="absolute top-0 left-0 size-6 border-t-2 border-l-2 border-white/80"></span>
                                <span class="absolute top-0 right-0 size-6 border-t-2 border-r-2 border-white/80"></span>
                                <span class="absolute bottom-0 left-0 size-6 border-b-2 border-l-2 border-white/80"></span>
                                <span class="absolute right-0 bottom-0 size-6 border-r-2 border-b-2 border-white/80"></span>
                                <span class="absolute inset-y-0 left-1/3 w-px bg-white/10"></span>
                                <span class="absolute inset-y-0 left-2/3 w-px bg-white/10"></span>
                                <span class="absolute inset-x-0 top-1/3 h-px bg-white/10"></span>
                                <span class="absolute inset-x-0 top-2/3 h-px bg-white/10"></span>
                            </div>
                            <div class="absolute inset-x-0 bottom-10 flex flex-col items-center gap-5">
                                <div class="flex gap-5 text-[0.625rem] font-semibold tracking-[0.12em] text-white/50">
                                    <span>{{ __('PHOTO') }}</span><span class="text-relais-400">{{ __('VIDÉO') }}</span><span>{{ __('AUDIO') }}</span>
                                </div>
                                <span class="grid size-16 place-items-center rounded-full border-[3px] border-white"><span class="size-11 rounded-full bg-relais-500"></span></span>
                            </div>
                        </div>

                        {{-- Écran 2 : vérification --}}
                        <div class="relative bg-white px-5 pt-14" data-story-screen>
                            <div class="absolute top-3 left-1/2 h-6 w-24 -translate-x-1/2 rounded-full bg-onyx"></div>
                            <p class="text-[0.6875rem] font-medium text-graphite">{{ __('Coordination · Yopougon') }}</p>
                            <p class="mt-1 font-display text-xl font-semibold tracking-tight">{{ __('Vérification') }}</p>
                            <ul class="mt-5 space-y-2.5 text-[0.8125rem]">
                                @foreach ([__('Lieu GPS conforme'), __('Date de prise de vue'), __('Média original'), __('Aucun doublon')] as $check)
                                    <li class="flex items-center gap-2.5 rounded-xl bg-mist px-3 py-2.5" data-check>
                                        <x-ui.icon name="check" class="size-4 text-status-validated" stroke="2.5" />{{ $check }}
                                    </li>
                                @endforeach
                            </ul>
                            <div class="absolute inset-x-0 bottom-12 flex flex-col items-center gap-2">
                                <svg viewBox="0 0 60 60" class="size-16" aria-hidden="true">
                                    <circle cx="30" cy="30" r="25" fill="none" stroke="var(--color-status-validated)" stroke-width="3" stroke-linecap="round" stroke-dasharray="160" stroke-dashoffset="0" transform="rotate(-90 30 30)" data-check-circle />
                                    <path d="M20 31l7 7 13-15" fill="none" stroke="var(--color-status-validated)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <p class="text-sm font-semibold text-status-validated">{{ __('Validé') }}</p>
                            </div>
                        </div>

                        {{-- Écran 3 : à l’antenne --}}
                        <div class="relative bg-black text-white" data-story-screen>
                            <div class="absolute top-3 left-1/2 h-6 w-24 -translate-x-1/2 rounded-full bg-onyx"></div>
                            <div class="absolute inset-x-3 top-12 z-10 flex items-start gap-2.5 rounded-2xl bg-white/90 p-3 text-onyx backdrop-blur-xl" data-story-notification>
                                <x-ui.logo compact class="shrink-0" />
                                <div class="text-[0.6875rem] leading-snug">
                                    <p class="font-semibold">JE RELAIS</p>
                                    <p class="text-onyx/75">{{ __('Votre relais vient d’être diffusé sur REFLET TV.') }}</p>
                                </div>
                            </div>
                            <div class="absolute inset-0 grid place-items-center" aria-hidden="true">
                                <span class="absolute size-40 animate-ring rounded-full border border-white/25"></span>
                                <span class="absolute size-40 animate-ring rounded-full border border-white/25 [animation-delay:1.8s]"></span>
                                <span class="size-2.5 rounded-full bg-relais-500"></span>
                            </div>
                            <div class="absolute inset-x-4 bottom-10">
                                <p class="inline-block bg-relais-500 px-2 py-0.5 text-[0.5625rem] font-bold tracking-[0.12em] uppercase">{{ __('Je relais') }}</p>
                                <p class="bg-white px-2.5 py-1.5 font-display text-[0.8125rem] font-semibold text-black">{{ __('Pluies diluviennes à Yopougon') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 4. CE QUE VOUS POUVEZ RELAYER (Annexe B) --}}
    <section class="bg-white px-5 py-32 sm:py-40">
        <div class="mx-auto max-w-[1024px]">
            <div class="flex flex-wrap items-end justify-between gap-6" data-anim>
                <h2 class="max-w-xl text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em]">{{ __('Ce que vous pouvez relayer.') }}</h2>
                <p class="max-w-xs text-lg leading-snug text-graphite">{{ __('Tout ce qui fait la vie de votre commune mérite d’être vu.') }}</p>
            </div>
            <div class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-categories>
                @foreach ([
                    ['bolt', __('Faits divers et accidents'), __('Accident de la circulation, incendie, effondrement')],
                    ['cloud-rain', __('Météo et environnement'), __('Pluies, inondations, érosion, insalubrité')],
                    ['home', __('Vie de quartier'), __('Travaux, coupures d’eau ou d’électricité, voirie')],
                    ['book', __('Éducation'), __('Écoles et universités, examens, rentrée')],
                    ['heart', __('Santé'), __('Centres de santé, campagnes, situations sanitaires')],
                    ['cart', __('Économie et consommation'), __('Marchés, prix, commerces, transport')],
                    ['hand', __('Société et solidarité'), __('Initiatives citoyennes, associations, portraits')],
                    ['music', __('Culture, sport et loisirs'), __('Concerts, compétitions locales, événements')],
                    ['landmark', __('Politique et institutions'), __('Vie municipale, cérémonies, services publics')],
                ] as [$icon, $title, $examples])
                    <article class="group relative overflow-hidden rounded-[1.5rem] bg-mist p-7 transition-colors duration-500 ease-out-expo hover:bg-onyx" data-category>
                        <span class="grid size-12 place-items-center rounded-2xl bg-white text-onyx transition-[transform,background-color,color] duration-700 ease-spring group-hover:scale-110 group-hover:-rotate-6 group-hover:bg-relais-500 group-hover:text-white">
                            <x-ui.icon :name="$icon" class="size-6" stroke="1.6" />
                        </span>
                        <h3 class="mt-10 text-xl font-semibold tracking-tight transition-colors duration-500 group-hover:text-white">{{ $title }}</h3>
                        <p class="mt-2 leading-relaxed text-graphite transition-colors duration-500 group-hover:text-white/60">{{ $examples }}</p>
                        <x-ui.icon name="arrow-up-right" class="absolute top-7 right-7 size-5 text-white opacity-0 transition-all duration-500 ease-out-expo group-hover:opacity-100" />
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 5. PARTOUT EN CÔTE D'IVOIRE : CARTE + TÉLÉSCRIPTEURS --}}
    <section class="overflow-hidden bg-black text-white" data-nav-theme="dark">
        <div class="mx-auto grid max-w-[1024px] items-center gap-16 px-5 pt-32 pb-20 sm:pt-40 md:grid-cols-[1fr_1.15fr]">
            <div>
                <p class="text-sm font-semibold tracking-[0.12em] text-relais-500 uppercase" data-anim>{{ __('Partout en Côte d’Ivoire') }}</p>
                <h2 class="mt-4 text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em] text-white" data-anim>{{ __('Un réseau dans chaque commune.') }}</h2>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-white/60" data-anim>{{ __('Des communes d’Abidjan aux capitales régionales, chaque relais est vérifié par un coordonnateur qui connaît le terrain, puis remonte vers la rédaction de REFLET TV.') }}</p>
                <dl class="mt-10 grid max-w-sm grid-cols-2 gap-6 border-t border-white/15 pt-8" data-anim>
                    <div>
                        <dt class="text-sm text-white/50">{{ __('Rattachement') }}</dt>
                        <dd class="mt-1 text-lg font-semibold">{{ __('Votre commune') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-white/50">{{ __('Vérification') }}</dt>
                        <dd class="mt-1 text-lg font-semibold">{{ __('Sur le terrain') }}</dd>
                    </div>
                </dl>
            </div>
            <div class="relative" data-anim>
                @include('public.partials.carte-civ')
            </div>
        </div>

        <div class="pb-24 sm:pb-32" aria-label="{{ __('Là où naît l’information') }}">
        @foreach (['left' => 'text-white', 'right' => 'text-white/30'] as $direction => $tone)
            <div class="overflow-hidden" data-marquee="{{ $direction }}" data-speed="{{ $direction === 'left' ? 46 : 54 }}">
                <div class="flex w-max items-center py-2 whitespace-nowrap {{ $tone }}">
                    @foreach ([1, 2] as $copy)
                        @foreach ($places as $place)
                            <span class="px-6 font-display text-[clamp(3rem,8.5vw,7.5rem)] leading-none font-semibold tracking-[-0.04em] sm:px-10" @if ($copy === 2) aria-hidden="true" @endif>{{ $place }}</span>
                            <span class="size-3 shrink-0 rounded-full bg-relais-500 sm:size-4" aria-hidden="true"></span>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    </section>

    {{-- 6. SUR LE TERRAIN --}}
    <section id="terrain" class="bg-white px-5 py-32 sm:py-40">
        <div class="mx-auto max-w-[1024px]">
            <div class="text-center" data-anim>
                <h2 class="text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em]">{{ __('Pensé pour le terrain.') }}</h2>
                <p class="mx-auto mt-5 max-w-xl text-xl leading-snug text-graphite">{{ __('Même avec une connexion faible, rien ne se perd.') }}</p>
            </div>

            <div class="mt-16 grid gap-5 md:grid-cols-2">
                <article class="flex min-h-[22rem] flex-col justify-between rounded-[1.75rem] bg-mist p-9 sm:p-11" data-anim>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-2 text-sm font-medium">
                            <span class="size-2 animate-blink rounded-full bg-relais-500"></span>{{ __('En attente du réseau') }}
                        </span>
                    </div>
                    <div>
                        <h3 class="text-3xl font-semibold tracking-tight">{{ __('Hors ligne.') }}</h3>
                        <p class="mt-3 max-w-sm text-lg leading-relaxed text-graphite">{{ __('Sans réseau, votre relais attend, chiffré dans le téléphone. Il part tout seul au retour de la connexion.') }}</p>
                    </div>
                </article>

                <article class="flex min-h-[22rem] flex-col justify-between rounded-[1.75rem] bg-mist p-9 sm:p-11" data-anim>
                    <div class="max-w-xs">
                        <div class="h-2 overflow-hidden rounded-full bg-black/10"><div class="h-full animate-resume rounded-full bg-relais-500"></div></div>
                        <p class="mt-3 text-sm font-medium text-graphite">{{ __('Envoi en cours… reprise automatique') }}</p>
                    </div>
                    <div>
                        <h3 class="text-3xl font-semibold tracking-tight">{{ __('Reprise automatique.') }}</h3>
                        <p class="mt-3 max-w-sm text-lg leading-relaxed text-graphite">{{ __('Une coupure ne fait pas tout recommencer : l’envoi reprend exactement là où il s’était arrêté.') }}</p>
                    </div>
                </article>

                <article class="flex min-h-[22rem] flex-col justify-between rounded-[1.75rem] bg-mist p-9 sm:p-11" data-anim>
                    <div class="flex h-14 items-center gap-1" aria-hidden="true">
                        @foreach (range(0, 27) as $bar)
                            <span class="h-full w-1 origin-center animate-wave rounded-full bg-onyx" style="animation-delay: -{{ ($bar * 137 % 100) / 100 }}s"></span>
                        @endforeach
                    </div>
                    <div>
                        <h3 class="text-3xl font-semibold tracking-tight">{{ __('Toutes les langues.') }}</h3>
                        <p class="mt-3 max-w-sm text-lg leading-relaxed text-graphite">{{ __('Décrivez ce que vous avez vu en note vocale, dans la langue de votre choix.') }}</p>
                    </div>
                </article>

                <article class="flex min-h-[22rem] flex-col justify-between rounded-[1.75rem] bg-mist p-9 sm:p-11" data-anim>
                    <div class="flex items-center gap-4 text-sm font-medium">
                        <span>{{ __('Crédité') }}</span>
                        <span class="relative h-7 w-12 rounded-full bg-onyx p-1" aria-hidden="true"><span class="block size-5 animate-toggle rounded-full bg-white"></span></span>
                        <span>{{ __('Anonyme') }}</span>
                    </div>
                    <div>
                        <h3 class="text-3xl font-semibold tracking-tight">{{ __('Anonymat au choix.') }}</h3>
                        <p class="mt-3 max-w-sm text-lg leading-relaxed text-graphite">{{ __('À chaque envoi, vous décidez : votre nom à l’antenne, ou aucune mention.') }}</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- 7. LA RÉDACTION : CONSOLE ANIMÉE --}}
    <section class="overflow-hidden bg-mist px-5 py-32 sm:py-40">
        <div class="mx-auto max-w-[1024px]">
            <div class="mx-auto max-w-2xl text-center" data-anim>
                <p class="text-sm font-semibold tracking-[0.12em] text-relais-600 uppercase">{{ __('Côté rédaction') }}</p>
                <h2 class="mt-4 text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em]">{{ __('Chaque relais compte. Aucun ne se perd.') }}</h2>
                <p class="mt-5 text-xl leading-snug text-graphite">{{ __('Dès votre envoi, votre relais arrive chez le coordonnateur de votre commune et sous les yeux de la rédaction.') }}</p>
            </div>
            <div class="mt-16" data-console-rise>
                @include('public.partials.console-apercu')
            </div>
            <p class="mt-4 text-center text-xs text-graphite">{{ __('Aperçu de la console REFLET TV — données de démonstration.') }}</p>
            <div class="mt-16 grid gap-10 sm:grid-cols-3">
                @foreach ([
                    ['inbox', __('Reçu tout de suite'), __('Votre relais apparaît dans la file de validation de votre commune.')],
                    ['shield', __('Vérifié sérieusement'), __('Lieu, date, origine du média : le coordonnateur contrôle avant toute exploitation.')],
                    ['bell', __('Vous êtes informé'), __('Une notification à chaque étape : reçu, validé, sélectionné, diffusé.')],
                ] as [$icon, $title, $text])
                    <div data-anim>
                        <x-ui.icon :name="$icon" class="size-7 text-relais-500" stroke="1.5" />
                        <h3 class="mt-4 text-xl font-semibold tracking-tight">{{ $title }}</h3>
                        <p class="mt-2 leading-relaxed text-graphite">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 8. APPELS À RELAIS --}}
    <section class="bg-white px-5 py-32 sm:py-40" data-calls>
        <div class="mx-auto grid max-w-[1024px] items-center gap-16 md:grid-cols-2">
            <div>
                <p class="text-sm font-semibold tracking-[0.12em] text-relais-600 uppercase" data-anim>{{ __('Appels à relais') }}</p>
                <h2 class="mt-4 text-[clamp(2.5rem,4.8vw,3.75rem)] leading-[1.04] font-semibold tracking-[-0.04em]" data-anim>{{ __('Quand l’actualité tombe, la rédaction vous appelle.') }}</h2>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-graphite" data-anim>{{ __('La rédaction cible une commune, un quartier ou un type de lieu, et envoie ses consignes aux Relais concernés. Vos réponses remontent en priorité.') }}</p>
                <ul class="mt-8 space-y-3" data-anim>
                    @foreach ([__('Zone ciblée : commune, région ou rayon autour d’un point'), __('Consignes claires et date limite'), __('Réponse directe depuis la notification')] as $point)
                        <li class="flex items-start gap-3 text-lg"><x-ui.icon name="check" class="mt-1 size-5 text-relais-500" stroke="2.25" />{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="mx-auto w-[min(80vw,320px)]">
                <div class="rounded-[3.2rem] bg-onyx p-3">
                    <div class="relative aspect-[9/19.5] overflow-hidden rounded-[2.5rem] bg-mist px-3 pt-24">
                        <div class="absolute top-3 left-1/2 h-6 w-24 -translate-x-1/2 rounded-full bg-onyx"></div>
                        <p class="absolute inset-x-0 top-10 text-center font-display text-4xl font-semibold tracking-tight text-onyx/90 tabular-nums">09:41</p>
                        <div class="space-y-2.5">
                            @foreach ([
                                [__('Appel à relais · Abobo'), __('Fortes pluies annoncées : envoyez vos images de la montée des eaux.'), __('maintenant')],
                                [__('Appel à relais · Adjamé'), __('Marché : relevez les prix du riz et de l’huile ce matin.'), __('il y a 2 min')],
                                [__('Appel à relais · Bouaké'), __('Rentrée scolaire : comment se passe l’accueil des élèves ?'), __('il y a 9 min')],
                                [__('Message de votre coordonnateur'), __('Merci pour votre relais d’hier, il a été validé.'), __('il y a 1 h')],
                            ] as [$title, $text, $when])
                                <div class="rounded-2xl bg-white/90 p-3 shadow-[0_8px_24px_-12px_rgb(0_0_0/0.25)] backdrop-blur-xl" data-call>
                                    <div class="flex items-center gap-2">
                                        <x-ui.logo compact class="scale-[0.8]" />
                                        <p class="flex-1 truncate text-[0.6875rem] font-semibold">{{ $title }}</p>
                                        <span class="text-[0.625rem] text-graphite">{{ $when }}</span>
                                    </div>
                                    <p class="mt-1.5 text-[0.75rem] leading-snug text-onyx/80">{{ $text }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 9. CHIFFRES --}}
    <section class="border-t border-black/10 bg-white px-5 py-28">
        <div class="mx-auto grid max-w-[1024px] gap-14 text-center sm:grid-cols-3">
            @foreach ([[2, __('minutes'), __('pour envoyer un relais')], [3, __('appuis'), __('au maximum pour relayer')], [30, __('minutes'), __('pour vérifier un relais urgent')]] as [$number, $unit, $label])
                <div data-anim>
                    <p class="font-display text-[clamp(4.5rem,10vw,7.5rem)] leading-none font-semibold tracking-[-0.05em] tabular-nums"><span data-counter="{{ $number }}">{{ $number }}</span></p>
                    <p class="mt-3 text-xl font-semibold tracking-tight">{{ $unit }}</p>
                    <p class="mt-1 text-graphite">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 10. DEVENIR RELAIS : DÉFILEMENT HORIZONTAL --}}
    <section id="devenir-relais" class="flex min-h-[100svh] flex-col justify-center overflow-hidden bg-mist py-24" data-hscroll>
        <div class="mx-auto w-full max-w-[1024px] px-5" data-anim>
            <h2 class="text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em]">{{ __('Devenir Relais.') }}</h2>
            <p class="mt-4 text-xl text-graphite">{{ __('Cinq étapes, depuis votre téléphone.') }}</p>
            <x-ui.button :href="route('relais.join')" size="lg" icon-trailing="arrow-right" class="mt-8">{{ __('Déposer ma candidature') }}</x-ui.button>
        </div>
        <div class="mt-14 overflow-x-auto md:overflow-visible [&::-webkit-scrollbar]:hidden">
            <ol class="flex w-max snap-x snap-mandatory gap-5 pr-5 pl-[max(1.25rem,calc((100vw-1024px)/2+1.25rem))]" data-hscroll-track>
                @foreach ([
                    [__('Créez votre compte'), __('Votre numéro de téléphone et un code reçu par SMS suffisent.')],
                    [__('Complétez votre dossier'), __('Identité, commune, quartier et pièce d’identité, protégée et chiffrée.')],
                    [__('Acceptez la charte'), __('Transmettre ce que l’on a vu, sans se mettre en danger, en respectant chacun.')],
                    [__('Suivez la formation'), __('Bien filmer, droit à l’image, fausses informations : quelques minutes, puis un quiz.')],
                    [__('Recevez votre badge'), __('Votre coordonnateur valide votre candidature. Vous êtes Relais.')],
                ] as $index => [$title, $text])
                    <li class="flex h-[26rem] w-[min(82vw,24rem)] shrink-0 snap-start flex-col justify-between rounded-[1.75rem] bg-white p-10">
                        <span class="font-display text-7xl font-semibold tracking-[-0.05em] text-black/10 tabular-nums">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h3 class="text-2xl font-semibold tracking-tight">{{ $title }}</h3>
                            <p class="mt-3 text-lg leading-relaxed text-graphite">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 11. CONFIANCE --}}
    <section id="confiance" class="bg-white px-5 py-32 sm:py-40">
        <div class="mx-auto max-w-[1024px]">
            <h2 class="max-w-2xl text-[clamp(2.75rem,6vw,4.5rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-anim>{{ __('Fiable pour la chaîne. Sûr pour vous.') }}</h2>
            <div class="mt-16 grid gap-x-10 gap-y-14 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['map-pin', __('Vérifié localement'), __('Chaque relais passe par le coordonnateur de la commune où il a été filmé.')],
                    ['shield', __('Données protégées'), __('Pièces d’identité chiffrées. Positions jamais publiées.')],
                    ['users', __('Personnes respectées'), __('Mineurs et personnes filmées : consignes de floutage transmises à la rédaction.')],
                    ['flag', __('Une charte claire'), __('Vous transmettez ce que vous avez vu, sans mise en scène ni danger.')],
                ] as [$icon, $title, $text])
                    <div data-anim>
                        <x-ui.icon :name="$icon" class="size-7 text-relais-500" stroke="1.5" />
                        <h3 class="mt-5 text-xl font-semibold tracking-tight">{{ $title }}</h3>
                        <p class="mt-2 leading-relaxed text-graphite">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 12. QUESTIONS --}}
    <section id="faq" class="border-t border-black/10 bg-white px-5 py-28">
        <div class="mx-auto max-w-[760px]">
            <h2 class="text-[clamp(2.25rem,4.5vw,3.5rem)] font-semibold tracking-[-0.035em]" data-anim>{{ __('Questions fréquentes.') }}</h2>
            <div class="mt-10 border-t border-black/10">
                @foreach ([
                    [__('Faut-il être journaliste pour devenir Relais ?'), __('Non. Un Relais est un observateur citoyen. La charte rappelle d’ailleurs qu’un Relais ne se présente jamais comme journaliste ou salarié de REFLET TV.')],
                    [__('Mon nom apparaîtra-t-il à l’antenne ?'), __('C’est vous qui choisissez, à chaque envoi : être crédité ou rester anonyme.')],
                    [__('Que se passe-t-il si je n’ai pas de réseau ?'), __('Votre relais est préparé et conservé chiffré sur votre téléphone, puis envoyé automatiquement au retour du réseau.')],
                    [__('Qui voit ma pièce d’identité ?'), __('Uniquement le coordonnateur de votre commune et les administrateurs habilités de REFLET TV.')],
                ] as [$question, $answer])
                    <details class="group border-b border-black/10" data-anim>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-6 text-xl font-semibold tracking-tight">
                            {{ $question }}
                            <span class="relative size-4 shrink-0" aria-hidden="true">
                                <span class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded-full bg-onyx"></span>
                                <span class="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 rounded-full bg-onyx transition-transform duration-500 ease-out-expo group-open:scale-y-0"></span>
                            </span>
                        </summary>
                        <p class="pb-7 text-lg leading-relaxed text-graphite">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 13. FINAL --}}
    <section class="bg-black px-5 py-40 text-center text-white sm:py-52" data-nav-theme="dark">
        <h2 class="text-[clamp(3.5rem,11vw,9rem)] leading-[0.95] font-semibold tracking-[-0.05em] text-white" data-finale-title>{{ __('Votre regard compte.') }}</h2>
        <p class="mx-auto mt-8 max-w-lg text-xl leading-snug text-white/60" data-anim>{{ __('L’application JE RELAIS arrive bientôt sur Android et iPhone.') }}</p>
        <div class="mt-10 flex flex-wrap justify-center gap-3" data-anim>
            <span class="rounded-full bg-white px-6 py-3 text-[1.0625rem] font-medium text-black" aria-disabled="true">{{ __('Bientôt sur l’App Store') }}</span>
            <span class="rounded-full px-6 py-3 text-[1.0625rem] font-medium text-white ring-1 ring-white/40" aria-disabled="true">{{ __('Bientôt sur Google Play') }}</span>
        </div>
    </section>
</x-layouts.public>
