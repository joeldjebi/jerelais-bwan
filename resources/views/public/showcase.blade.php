@php
    use App\Domain\Editorial\Support\CreditLine;
    use App\Http\Controllers\Public\ShowcaseController;

    $periods = ['semaine' => __('7 derniers jours'), 'mois' => __('30 derniers jours'), 'annee' => __('12 derniers mois')];
    $active = array_filter([
        'q' => $filters['q'] !== '' ? '« '.$filters['q'].' »' : null,
        'commune' => $communes->firstWhere('slug', $filters['commune'])?->name,
        'categorie' => $categories->firstWhere('slug', $filters['categorie'])?->name,
        'periode' => $periods[$filters['periode']] ?? null,
    ]);
    $without = fn (string $key): string => route('showcase', array_filter([...$filters, $key => '', 'tri' => $filters['tri'] === 'anciens' ? 'anciens' : null]));
    $field = 'h-12 w-full rounded-full bg-white px-5 text-[1rem] text-onyx ring-1 ring-black/10 transition outline-none placeholder:text-graphite/70 focus:ring-2 focus:ring-relais-500';
    $selectClass = $field.' appearance-none pr-11';
@endphp

<x-layouts.public :title="$items->currentPage() > 1 ? __('Vu sur REFLET TV · page :page', ['page' => $items->currentPage()]) : __('Vu sur REFLET TV')" :description="__('Les relais des observateurs citoyens passés à l’antenne de REFLET TV, par commune et par sujet.')">
    <section class="bg-white px-5 pt-32 pb-12 sm:pt-40">
        <div class="mx-auto max-w-[1024px]">
            <p class="text-[0.9375rem] font-medium text-relais-600" data-anim>{{ __('JE RELAIS') }}</p>
            <h1 class="mt-3 text-[clamp(2.75rem,7vw,5rem)] leading-[1] font-semibold tracking-[-0.045em]" data-anim>{{ __('Vu sur REFLET TV.') }}</h1>
            <p class="mt-5 max-w-xl text-xl text-graphite" data-anim>
                {{ __('Ce que les Relais ont vu, et que la chaîne a montré.') }}
                @if ($total > 0)<span class="text-onyx">{{ trans_choice(':count diffusion à revoir.|:count diffusions à revoir.', $total) }}</span>@endif
            </p>

            @if ($total > 0)
                {{-- Filtres : simple formulaire GET, utilisable sans JavaScript ; envoyé automatiquement à chaque choix. --}}
                <form method="GET" action="{{ route('showcase') }}" role="search" aria-label="{{ __('Filtrer les diffusions') }}" data-autosubmit
                    class="mt-10 rounded-[1.75rem] bg-mist p-3 sm:p-4">
                    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-[1.25fr_1.15fr_1fr_1fr]">
                        <label class="relative block sm:col-span-2 lg:col-span-1">
                            <span class="sr-only">{{ __('Rechercher') }}</span>
                            <svg class="pointer-events-none absolute top-1/2 left-4.5 size-[1.125rem] -translate-y-1/2 text-graphite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="80" placeholder="{{ __('Sujet, lieu, émission…') }}" class="{{ $field }} pl-12" autocomplete="off" enterkeyhint="search">
                        </label>
                        @foreach ([
                            'commune' => [__('Commune'), __('Toutes les communes'), $communes->pluck('name', 'slug')],
                            'categorie' => [__('Catégorie'), __('Tous les sujets'), $categories->pluck('name', 'slug')],
                            'periode' => [__('Période'), __('Toutes les dates'), collect($periods)],
                        ] as $name => [$label, $placeholder, $options])
                            <label class="relative block">
                                <span class="sr-only">{{ $label }}</span>
                                <select name="{{ $name }}" class="{{ $selectClass }}">
                                    <option value="">{{ $placeholder }}</option>
                                    @foreach ($options as $value => $text)
                                        <option value="{{ $value }}" @selected($filters[$name] === $value)>{{ $text }}</option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute top-1/2 right-4.5 size-4 -translate-y-1/2 text-graphite" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 px-1">
                        <div class="flex items-center gap-2 text-[0.9375rem] text-graphite">
                            <label for="tri">{{ __('Trier :') }}</label>
                            <select id="tri" name="tri" class="appearance-none bg-transparent pr-1 font-medium text-onyx outline-none focus-visible:underline">
                                <option value="recents" @selected($filters['tri'] === 'recents')>{{ __('les plus récentes') }}</option>
                                <option value="anciens" @selected($filters['tri'] === 'anciens')>{{ __('les plus anciennes') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="h-11 rounded-full bg-onyx px-6 text-[0.9375rem] font-medium text-white transition hover:bg-black" data-autosubmit-button>{{ __('Afficher') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="bg-mist px-5 pt-12 pb-24" id="diffusions" aria-live="polite">
        <div class="mx-auto max-w-[1024px]">
            @if ($total > 0)
                <div class="mb-8 flex flex-wrap items-center gap-2.5">
                    <p class="mr-2 text-[1.0625rem] font-semibold text-onyx">
                        {{ trans_choice(':count diffusion|:count diffusions', $items->total()) }}
                        @if ($items->lastPage() > 1)<span class="font-normal text-graphite">· {{ __('page :page sur :last', ['page' => $items->currentPage(), 'last' => $items->lastPage()]) }}</span>@endif
                    </p>
                    @foreach ($active as $key => $label)
                        <a href="{{ $without($key) }}#diffusions" class="group inline-flex items-center gap-1.5 rounded-full bg-white px-3.5 py-1.5 text-[0.875rem] font-medium text-onyx ring-1 ring-black/10 transition hover:ring-black/25" aria-label="{{ __('Retirer le filtre :filter', ['filter' => $label]) }}">
                            {{ $label }}
                            <svg class="size-3.5 text-graphite transition group-hover:text-onyx" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </a>
                    @endforeach
                    @if (count($active) > 1)
                        <a href="{{ route('showcase') }}#diffusions" class="text-[0.875rem] font-medium text-relais-600 hover:underline">{{ __('Tout effacer') }}</a>
                    @endif
                </div>
            @endif

            @if ($items->isEmpty())
                <div class="rounded-[1.75rem] bg-white px-6 py-16 text-center">
                    @if ($total === 0)
                        <p class="text-xl text-graphite">{{ __('Les premiers relais diffusés apparaîtront bientôt ici.') }}</p>
                    @else
                        <p class="text-xl font-semibold text-onyx">{{ __('Aucune diffusion ne correspond.') }}</p>
                        <p class="mt-2 text-graphite">{{ __('Essayez une autre commune, un autre sujet ou une période plus large.') }}</p>
                        <a href="{{ route('showcase') }}" class="mt-6 inline-flex h-11 items-center rounded-full bg-onyx px-6 text-[0.9375rem] font-medium text-white hover:bg-black">{{ __('Voir toutes les diffusions') }}</a>
                    @endif
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $relais)
                        @php $date = $relais->broadcast_at ?? $relais->showcased_at; @endphp
                        <article class="flex flex-col overflow-hidden rounded-[1.75rem] bg-white" data-anim>
                            <div class="relative aspect-[4/3] bg-onyx">
                                @if (ShowcaseController::hasVideo($relais))
                                    <video src="{{ route('showcase.video', $relais) }}" poster="{{ route('showcase.image', $relais) }}" controls playsinline preload="none" class="size-full object-cover" aria-label="{{ $relais->showcase_title }}"></video>
                                @elseif ($imageUrl = ShowcaseController::imageUrl($relais))
                                    <img src="{{ $imageUrl }}" alt="" class="size-full object-cover" loading="lazy" decoding="async" width="640" height="480">
                                @else
                                    <div class="grid size-full place-items-center"><x-ui.icon name="signal" class="size-10 text-white/30" /></div>
                                @endif
                                @if ($relais->commune)
                                    <a href="{{ route('showcase', ['commune' => $relais->commune->slug]) }}#diffusions" class="absolute top-4 left-4 rounded-full bg-white/90 px-3 py-1 text-[0.75rem] font-semibold text-onyx backdrop-blur transition hover:bg-white">{{ $relais->commune->name }}</a>
                                @endif
                            </div>
                            <div class="flex flex-1 flex-col p-6">
                                <p class="text-[0.8125rem] font-medium text-relais-600">
                                    {{ collect([$relais->category?->name, $date ? $date->timezone('Africa/Abidjan')->translatedFormat('j F Y') : null])->filter()->join(' · ') }}
                                </p>
                                <h2 class="mt-2 text-[1.25rem] leading-snug font-semibold tracking-[-0.02em] text-onyx">{{ $relais->showcase_title }}</h2>
                                @if ($relais->showcase_text)<p class="mt-2 line-clamp-4 leading-relaxed text-graphite">{{ $relais->showcase_text }}</p>@endif
                                <div class="mt-auto pt-5">
                                    <p class="text-[0.8125rem] text-graphite">
                                        {{ CreditLine::for($relais) }}@if ($relais->broadcast_programme) · {{ $relais->broadcast_programme }}@endif
                                    </p>
                                    @if ($relais->broadcast_url)
                                        <a href="{{ $relais->broadcast_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-[0.9375rem] font-medium text-relais-600 hover:underline">{{ __('Voir la diffusion') }}<x-ui.icon name="arrow-up-right" class="size-4" /></a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @include('public.partials.pagination', ['paginator' => $items])
            @endif
        </div>
    </section>
</x-layouts.public>
