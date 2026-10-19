@php
    use App\Domain\Contribution\Support\RelaisVerification;
    use App\Domain\Media\Enums\MediaStatus;

    $r = $this->relais;
    $verification = app(RelaisVerification::class);
    $map = $verification->map($r);
    $warnings = $verification->warnings($r);
    $medias = $r->medias->where('status', MediaStatus::Ready)->values();
    $pendingMedias = $r->medias->whereIn('status', [MediaStatus::Pending, MediaStatus::Uploaded, MediaStatus::Processing])->count();
    $rejectedMedias = $r->medias->where('status', MediaStatus::Rejected);
    $stats = $this->stats;
    $needsBlur = app(\App\Domain\Contribution\Actions\DecideRelaisItem::class)->requiresBlurAnswer($r);
    $urgencyTone = ['urgent' => 'danger', 'important' => 'warning', 'normal' => 'neutral'];
    $stateTone = ['received' => 'info', 'in_review' => 'accent', 'complement_requested' => 'warning', 'sensitive' => 'warning', 'validated' => 'success', 'selected' => 'success', 'broadcast' => 'success', 'rejected' => 'danger', 'uploading' => 'neutral', 'archived' => 'neutral'];
    $textarea = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
@endphp

<div class="space-y-6">
    <a href="{{ route('console.validation') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Validation des relais') }}
    </a>

    <header class="flex flex-wrap items-start justify-between gap-4" data-enter>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <x-console.badge :tone="$stateTone[$r->state->getValue()] ?? 'neutral'">{{ $r->state->label() }}</x-console.badge>
                <x-console.badge :tone="$urgencyTone[$r->urgency->value]">{{ $r->urgency->label() }}</x-console.badge>
                @if ($r->isOverdue())<x-console.badge tone="danger">{{ __('Délai dépassé') }}</x-console.badge>@endif
            </div>
            <h1 class="mt-2 text-[clamp(1.75rem,3.5vw,2.5rem)] leading-tight font-bold tracking-[-0.03em]">{{ $r->title ?: __('Sans titre') }}</h1>
            <p class="mt-1 text-[0.9375rem] text-mute">
                {{ collect([$r->category?->name, $r->commune?->name, __('reçu :when', ['when' => $r->received_at?->diffForHumans()])])->filter()->join(' · ') }}
            </p>
        </div>
        @if ($r->locked_by === auth()->id() && $r->locked_until?->isFuture())
            <div class="flex items-center gap-2 text-[0.8125rem] text-mute">
                <x-ui.icon name="shield" class="size-4 text-tile-green" />
                {{ __('Réservé pour vous jusqu’à :time', ['time' => $r->locked_until->timezone('Africa/Abidjan')->format('H:i')]) }}
                <button type="button" wire:click="release" class="font-medium text-tile-blue hover:opacity-80">{{ __('Libérer') }}</button>
            </div>
        @endif
    </header>

    @if ($lockMessage)
        <div class="flex items-center gap-3 rounded-[1.2rem] bg-status-pending/12 p-4 text-[0.9375rem]" role="status" data-enter>
            <x-console.tile icon="shield" tint="orange" />
            <p><span class="font-semibold">{{ __('Lecture seule.') }}</span> {{ $lockMessage }}</p>
        </div>
    @endif

    @if ($this->duplicates !== [])
        <div class="rounded-[1.2rem] bg-status-rejected/[0.08] p-4" role="alert" data-enter>
            <div class="flex items-center gap-3">
                <x-console.tile icon="layers" tint="pink" />
                <p class="text-[0.9375rem] font-semibold">{{ __('Doublon possible (COO-13)') }}</p>
            </div>
            <ul class="mt-2 space-y-1 pl-11 text-[0.875rem]">
                @foreach ($this->duplicates as $match)
                    <li>
                        <x-console.badge tone="danger">{{ $match['identical'] ? __('Média identique') : __('Média très proche') }}</x-console.badge>
                        <span class="font-medium">{{ Gate::allows('view', $match['other']->relaisItem) ? ($match['other']->relaisItem->title ?: __('Sans titre')) : __('Relais d’une autre commune') }}</span>
                        <span class="text-mute">· {{ $match['other']->relaisItem->commune?->name }} · {{ $match['other']->relaisItem->state->label() }} · {{ $date($match['other']->relaisItem->received_at) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_23rem]">
        <div class="space-y-6">
            {{-- Lecteur intégré (COO-11) --}}
            <section class="overflow-hidden rounded-[1.6rem] bg-[#0b0b0d] text-white shadow-[0_18px_40px_-20px_rgb(0_0_0/0.6)]" data-enter
                x-data="{ current: 0, speed: 1, zoom: false, fps: 25,
                    step(delta) { const el = this.$refs['m' + this.current]; if (el) { el.pause(); el.currentTime = Math.max(0, el.currentTime + delta / this.fps) } },
                    setSpeed(value) { this.speed = value; const el = this.$refs['m' + this.current]; if (el) el.playbackRate = value } }">
                @if ($medias->isEmpty())
                    <div class="grid aspect-video place-items-center text-[0.9375rem] text-white/60">
                        {{ $pendingMedias > 0 ? __('Médias en cours de contrôle…') : __('Aucun média lisible') }}
                    </div>
                @else
                    <div class="relative grid aspect-video place-items-center bg-black">
                        @foreach ($medias as $index => $media)
                            <div x-show="current === {{ $index }}" @if ($index > 0) x-cloak @endif class="absolute inset-0 grid place-items-center">
                                @switch($media->kind->value)
                                    @case('photo')
                                        <button type="button" x-on:click="zoom = ! zoom" class="size-full overflow-auto" :class="zoom ? 'cursor-zoom-out' : 'cursor-zoom-in'">
                                            <img src="{{ route('console.medias.show', $media) }}" alt="" class="mx-auto transition-transform duration-500 ease-spring" :class="zoom ? 'max-w-none scale-[1.8]' : 'max-h-full max-w-full object-contain'" loading="lazy">
                                        </button>
                                        @break
                                    @case('video')
                                        <video x-ref="m{{ $index }}" src="{{ route('console.medias.show', $media) }}" controls playsinline preload="metadata" class="max-h-full max-w-full"
                                            @if ($thumb = $media->variants->firstWhere('kind', 'thumbnail')) poster="{{ route('console.medias.show', [$media, 'thumbnail']) }}" @endif></video>
                                        @break
                                    @default
                                        <div class="flex w-full max-w-md flex-col items-center gap-5 px-6">
                                            <span class="grid size-20 place-items-center rounded-full bg-white/10"><x-ui.icon name="mic" class="size-9" /></span>
                                            <audio x-ref="m{{ $index }}" src="{{ route('console.medias.show', $media) }}" controls preload="metadata" class="w-full"></audio>
                                        </div>
                                @endswitch
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-4 py-3">
                        <div class="flex gap-2 overflow-x-auto">
                            @foreach ($medias as $index => $media)
                                <button type="button" x-on:click="current = {{ $index }}; zoom = false" class="relative size-12 shrink-0 overflow-hidden rounded-lg ring-2 transition" :class="current === {{ $index }} ? 'ring-relais-500' : 'ring-transparent opacity-60 hover:opacity-100'">
                                    @if ($media->variants->firstWhere('kind', 'thumbnail'))
                                        <img src="{{ route('console.medias.show', [$media, 'thumbnail']) }}" alt="" class="size-full object-cover">
                                    @else
                                        <span class="grid size-full place-items-center bg-white/10"><x-ui.icon name="mic" class="size-5" /></span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-1 text-[0.8125rem]">
                            <button type="button" x-on:click="step(-1)" class="rounded-full px-2.5 py-1 hover:bg-white/10" title="{{ __('Image précédente') }}">−1 img</button>
                            <button type="button" x-on:click="step(1)" class="rounded-full px-2.5 py-1 hover:bg-white/10" title="{{ __('Image suivante') }}">+1 img</button>
                            <span class="mx-1 h-4 w-px bg-white/20"></span>
                            @foreach ([0.25, 0.5, 1, 2] as $rate)
                                <button type="button" x-on:click="setSpeed({{ $rate }})" class="rounded-full px-2.5 py-1 tabular-nums" :class="speed === {{ $rate }} ? 'bg-white text-black' : 'hover:bg-white/10'">{{ $rate }}×</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            @if ($rejectedMedias->isNotEmpty())
                <p class="text-[0.8125rem] text-status-rejected" data-enter>
                    {{ trans_choice(':count média refusé au contrôle|:count médias refusés au contrôle', $rejectedMedias->count()) }} :
                    {{ $rejectedMedias->map(fn ($media) => __("media.rejections.{$media->rejection}"))->unique()->join(' ') }}
                </p>
            @endif

            <x-console.group :title="__('Description')">
                <p class="px-5 py-4 text-[0.9375rem] leading-relaxed whitespace-pre-line">{{ $r->description ?: '—' }}</p>
            </x-console.group>

            {{-- Protection des personnes (MOB-15, COO-16) --}}
            <x-console.group :title="__('Protection des personnes')">
                <ul class="text-[0.9375rem]">
                    <li class="flex items-center gap-3.5 px-4 py-3">
                        <x-console.tile icon="users" :tint="$r->has_minors ? 'orange' : 'gray'" />
                        <span class="flex-1">{{ __('Mineurs identifiables') }}</span>
                        <x-console.badge :tone="$r->has_minors ? 'warning' : 'neutral'">{{ $r->has_minors ? __('Oui') : __('Non') }}</x-console.badge>
                    </li>
                    <li class="flex items-center gap-3.5 px-4 py-3">
                        <x-console.tile icon="hand" tint="blue" />
                        <span class="flex-1">{{ __('Accord des personnes filmées') }}</span>
                        <span class="text-mute">{{ $r->people_consent?->label() ?? '—' }}</span>
                    </li>
                    <li class="flex items-center gap-3.5 px-4 py-3">
                        <x-console.tile icon="signal" tint="purple" />
                        <span class="flex-1">{{ __('À l’antenne') }}</span>
                        <span class="text-mute">{{ $r->credit_mode?->label() }}@if ($r->credit_name) · {{ $r->credit_name }}@endif</span>
                    </li>
                    @if ($r->blur_required !== null)
                        <li class="flex items-center gap-3.5 px-4 py-3">
                            <x-console.tile icon="eye-off" :tint="$r->blur_required ? 'pink' : 'green'" />
                            <span class="flex-1">{{ __('Floutage') }}</span>
                            <x-console.badge :tone="$r->blur_required ? 'danger' : 'success'">{{ $r->blur_required ? __('Nécessaire') : __('Inutile') }}</x-console.badge>
                        </li>
                    @endif
                </ul>
            </x-console.group>

            <x-console.group :title="__('Historique')">
                <ol class="px-4 py-2">
                    @foreach ($r->statusHistory as $change)
                        <li class="flex items-start gap-3 py-2 text-[0.875rem]">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-tile-blue"></span>
                            <span class="flex-1">
                                <span class="font-medium">{{ __("relais_items.states.{$change->to}") }}</span>
                                <span class="text-mute">· {{ $change->actor?->name ?? __('Relais') }} · {{ $date($change->created_at) }}</span>
                                @if ($change->reason)<span class="block text-mute">« {{ $change->reason }} »</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-console.group>
        </div>

        {{-- Panneau de vérification (COO-12) --}}
        <aside class="space-y-6">
            @if ($this->canEdit || $this->canExport)
                @php $stateName = $r->state->getValue(); @endphp
                <x-console.group :title="__('Exploitation')" data-enter>
                    @if ($r->assignee || $r->programme || $r->broadcast_at)
                        <ul class="border-b border-line text-[0.875rem]">
                            @if ($r->assignee)<li class="flex gap-3 px-4 py-2.5"><span class="w-24 shrink-0 text-mute">{{ __('Affecté à') }}</span><span>{{ $r->assignee->name }}</span></li>@endif
                            @if ($r->programme)<li class="flex gap-3 px-4 py-2.5"><span class="w-24 shrink-0 text-mute">{{ __('Émission') }}</span><span>{{ $r->programme }}</span></li>@endif
                            @if ($r->broadcast_at)
                                <li class="flex gap-3 px-4 py-2.5"><span class="w-24 shrink-0 text-mute">{{ __('Diffusé') }}</span>
                                    <span>{{ $date($r->broadcast_at) }} · {{ $r->broadcast_programme }}@if ($r->broadcast_url) · <a href="{{ $r->broadcast_url }}" target="_blank" rel="noopener" class="text-tile-blue">{{ __('lien') }}</a>@endif</span></li>
                            @endif
                        </ul>
                    @endif
                    <div class="flex flex-wrap gap-2 p-3">
                        @if ($this->canEdit)
                            @if ($stateName === 'sensitive')
                                <x-ui.button size="sm" variant="success" wire:click="openEditorial('arbitrate-validate')">{{ __('Valider (arbitrage)') }}</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="openEditorial('arbitrate-reject')">{{ __('Rejeter (arbitrage)') }}</x-ui.button>
                            @elseif ($stateName === 'rejected')
                                <x-ui.button size="sm" variant="secondary" wire:click="openEditorial('arbitrate-validate')">{{ __('Revenir sur le rejet') }}</x-ui.button>
                            @endif
                            @if (in_array($stateName, ['validated', 'selected'], true))
                                <x-ui.button size="sm" wire:click="openEditorial('select')">{{ $stateName === 'selected' ? __('Modifier l’affectation') : __('Sélectionner') }}</x-ui.button>
                            @endif
                            @if ($stateName === 'validated')
                                <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="openEditorial('arbitrate-reject')">{{ __('Revenir sur la validation') }}</x-ui.button>
                            @endif
                            @if ($stateName === 'selected')
                                <x-ui.button size="sm" variant="success" icon="signal" wire:click="openEditorial('broadcast')">{{ __('Marquer diffusé') }}</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="unselect">{{ __('Retirer') }}</x-ui.button>
                            @endif
                            <x-ui.button size="sm" variant="secondary" icon="layers" wire:click="openEditorial('collection')">{{ __('Collection') }}</x-ui.button>
                        @endif
                        @if ($this->canExport)
                            @if ($r->isExploitable())
                                <form method="POST" action="{{ route('console.export.relais', $r) }}">
                                    @csrf
                                    <x-ui.button size="sm" type="submit" variant="dark" icon="arrow-up-right">{{ __('Télécharger (ZIP + fiche)') }}</x-ui.button>
                                </form>
                            @elseif (auth()->user()->can(\App\Domain\Access\Enums\Permission::RelaisExportUnvalidated->value))
                                <x-ui.button size="sm" variant="secondary" icon="shield" wire:click="openEditorial('derogation')">{{ __('Exporter par dérogation') }}</x-ui.button>
                            @else
                                <span class="px-1 py-1.5 text-[0.8125rem] text-mute">{{ __('Export bloqué tant que le relais n’est pas validé.') }}</span>
                            @endif
                        @endif
                    </div>
                    @if ($r->collections->isNotEmpty())
                        <p class="border-t border-line px-4 py-2.5 text-[0.8125rem] text-mute">{{ __('Collections') }} : {{ $r->collections->pluck('name')->join(', ') }}</p>
                    @endif
                </x-console.group>
            @endif
            @if ($map)
                <section class="overflow-hidden rounded-[1.6rem] bg-surface shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)]" data-enter>
                    <div class="relative h-56 overflow-hidden bg-black/[0.04]">
                        <div class="absolute" style="width: 768px; height: 768px; left: calc(50% - {{ $map['marker_left'] }}px); top: calc(50% - {{ $map['marker_top'] }}px)">
                            @foreach ($map['tiles'] as $tile)
                                <img src="{{ $tile['url'] }}" alt="" class="absolute size-64 max-w-none select-none" style="left: {{ $tile['left'] }}px; top: {{ $tile['top'] }}px" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                            @endforeach
                        </div>
                        <span class="absolute top-1/2 left-1/2 grid size-9 -translate-x-1/2 -translate-y-full place-items-center">
                            <svg viewBox="0 0 24 32" class="size-9 drop-shadow-[0_4px_6px_rgb(0_0_0/0.35)]" aria-hidden="true"><path d="M12 0C5.4 0 0 5.2 0 11.7 0 20.5 12 32 12 32s12-11.5 12-20.3C24 5.2 18.6 0 12 0Z" fill="#f28c28"/><circle cx="12" cy="11.5" r="4.5" fill="#fff"/></svg>
                        </span>
                        @if ($r->location_accuracy_m)
                            <span class="absolute bottom-2 left-2 rounded-full bg-white/90 px-2 py-0.5 text-[0.6875rem] font-medium text-black">± {{ $r->location_accuracy_m }} m</span>
                        @endif
                        <span class="absolute right-2 bottom-2 rounded bg-white/80 px-1 text-[0.5625rem] text-black">© OpenStreetMap</span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 text-[0.8125rem]">
                        <span class="text-mute tabular-nums">{{ number_format($r->location->getLatitude(), 5) }}, {{ number_format($r->location->getLongitude(), 5) }}</span>
                        <a href="{{ $map['osm_url'] }}" target="_blank" rel="noopener" class="font-medium text-tile-blue hover:opacity-80">{{ __('Ouvrir la carte') }}</a>
                    </div>
                </section>
            @endif

            @if ($warnings !== [])
                <div class="space-y-2" data-enter>
                    @foreach ($warnings as $warning)
                        <p class="flex items-start gap-2.5 rounded-[1rem] bg-status-pending/12 px-3.5 py-2.5 text-[0.8125rem] leading-snug"><x-ui.icon name="bell" class="mt-0.5 size-4 shrink-0 text-[#a05a00]" />{{ $warning }}</p>
                    @endforeach
                </div>
            @endif

            <x-console.group :title="__('Lieu et dates')">
                <ul class="text-[0.875rem]">
                    @foreach (array_filter([
                        __('Commune') => $r->commune?->name.($r->routed_by === 'gps' ? ' · '.__('par GPS') : ' · '.__('déclarée')),
                        __('Lieu indiqué') => $r->declared_place,
                        __('Prise de vue') => $date($r->medias->pluck('captured_at')->filter()->min()),
                        __('Appareil') => $date($r->device_captured_at),
                        __('Reçu') => $date($r->received_at),
                        __('Délai cible') => $date($r->sla_due_at),
                        __('Téléphone') => $r->device_model,
                    ]) as $label => $value)
                        <li class="flex gap-3 px-4 py-2.5">
                            <span class="w-24 shrink-0 text-mute">{{ $label }}</span>
                            <span class="min-w-0 flex-1">{{ $value }}</span>
                        </li>
                    @endforeach
                    <li class="flex gap-3 px-4 py-2.5">
                        <span class="w-24 shrink-0 text-mute">{{ __('Origine') }}</span>
                        <span class="flex-1">{{ $r->medias->contains('is_imported', true) ? __('Importé de la galerie') : __('Capturé dans l’application') }}</span>
                    </li>
                </ul>
            </x-console.group>

            <x-console.group :title="__('Relais')">
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <x-console.tile icon="signal" tint="brand" />
                    <div class="flex-1">
                        <p class="text-[0.9375rem] font-semibold">{{ $r->relaisProfile?->display_name ?? $r->user->name }}</p>
                        <p class="text-[0.8125rem] text-mute">{{ $r->relaisProfile?->number }}</p>
                    </div>
                    @if ($stats['reliability'] !== null)
                        <span @class(['text-[1.25rem] font-bold tabular-nums', 'text-tile-green' => $stats['reliability'] >= 70, 'text-[#a05a00]' => $stats['reliability'] < 70])>{{ $stats['reliability'] }} %</span>
                    @endif
                </div>
                <div class="grid grid-cols-3 border-t border-line text-center">
                    @foreach ([__('Envoyés') => $stats['sent'], __('Validés') => $stats['validated'], __('Rejetés') => $stats['rejected']] as $label => $value)
                        <div class="px-2 py-3"><p class="text-[1.125rem] font-bold tabular-nums">{{ $value }}</p><p class="text-[0.75rem] text-mute">{{ $label }}</p></div>
                    @endforeach
                </div>
            </x-console.group>

            @can('validate', $r)
                @if ($r->relaisProfile)
                    <x-ui.button variant="secondary" icon="inbox" class="w-full" wire:click="writeToRelais">{{ __('Écrire au Relais') }}</x-ui.button>
                @endif
                <x-console.group :title="__('Note interne')" :footer="__('Visible par l’équipe et la rédaction, jamais par le Relais.')">
                    <div class="p-3">
                        <textarea wire:model="note" wire:blur="saveNote" rows="3" class="{{ $textarea }} mt-0!" placeholder="{{ __('Ex. : appelé le Relais, confirme être sur place.') }}"></textarea>
                    </div>
                </x-console.group>
            @endcan
        </aside>
    </div>

    {{-- Barre de décision (COO-14) --}}
    @if ($this->canDecide)
        <div class="sticky bottom-4 z-30 flex flex-wrap items-center justify-end gap-2 rounded-[1.4rem] bg-surface/85 p-3 shadow-[0_0_0_0.5px_rgb(0_0_0/0.08),0_20px_50px_-20px_rgb(0_0_0/0.35)] backdrop-blur-2xl" data-enter>
            <span class="mr-auto pl-2 text-[0.8125rem] text-mute">{{ __('Votre décision') }}</span>
            <x-ui.button variant="ghost" class="text-status-rejected!" wire:click="open('reject')">{{ __('Rejeter') }}</x-ui.button>
            <x-ui.button variant="secondary" wire:click="open('sensitive')">{{ __('Sensible') }}</x-ui.button>
            <x-ui.button variant="secondary" wire:click="open('complement')">{{ __('Complément') }}</x-ui.button>
            <x-ui.button variant="success" icon="check" wire:click="open('validate')">{{ __('Valider') }}</x-ui.button>
        </div>
    @endif

    <div x-data="{ open: $wire.entangle('sheet') }" x-cloak>
        <div x-show="open !== ''" x-transition.opacity.duration.300ms class="fixed inset-0 z-[55] bg-black/30 backdrop-blur-[3px]" x-on:click="open = ''"></div>
        <div x-show="open !== ''" x-on:keydown.escape.window="open = ''" class="fixed inset-0 z-[56] flex items-end justify-center sm:items-center sm:p-6" x-on:click.self="open = ''">
            <div x-show="open !== ''" x-transition:enter="transition duration-500 ease-spring" x-transition:enter-start="opacity-0 translate-y-16 sm:translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[1.6rem] bg-canvas shadow-[0_40px_100px_-30px_rgb(0_0_0/0.5)] sm:max-w-lg sm:rounded-[1.6rem]" role="dialog" aria-modal="true">
                <form wire:submit="decide('{{ $sheet ?: 'validate' }}')" class="flex min-h-0 flex-1 flex-col">
                    <header class="px-6 pt-6 pb-3">
                        <h2 class="text-[1.375rem] font-bold tracking-[-0.02em]">
                            @switch($sheet)
                                @case('validate') {{ __('Valider le relais') }} @break
                                @case('reject') {{ __('Rejeter le relais') }} @break
                                @case('complement') {{ __('Demander un complément') }} @break
                                @case('sensitive') {{ __('Transmettre à la rédaction') }} @break
                            @endswitch
                        </h2>
                        <p class="mt-1 text-[0.875rem] text-mute">
                            @switch($sheet)
                                @case('validate') {{ __('Corrigez si besoin le titre, la description, la catégorie ou le lieu.') }} @break
                                @case('reject') {{ __('Le motif est communiqué au Relais.') }} @break
                                @case('complement') {{ __('Le Relais reçoit votre question et peut ajouter un média.') }} @break
                                @case('sensitive') {{ __('Sujet délicat : la rédaction décide.') }} @break
                            @endswitch
                        </p>
                    </header>

                    <div class="flex-1 space-y-4 overflow-y-auto px-6 pb-6">
                        @if ($sheet === 'validate')
                            <x-console.input :label="__('Titre')" name="corrections.title" wire:model="corrections.title" />
                            <div>
                                <label for="corr-description" class="block text-[0.8125rem] font-medium">{{ __('Description') }}</label>
                                <textarea id="corr-description" wire:model="corrections.description" rows="3" class="{{ $textarea }}"></textarea>
                            </div>
                            <x-console.select :label="__('Catégorie')" name="corrections.category" wire:model="corrections.category">
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->uuid }}">{{ $category->name }}</option>
                                @endforeach
                            </x-console.select>
                            <x-console.input :label="__('Lieu')" name="corrections.declared_place" wire:model="corrections.declared_place" />
                            @if ($needsBlur)
                                <fieldset class="rounded-[1rem] bg-status-pending/12 p-4">
                                    <legend class="sr-only">{{ __('Floutage') }}</legend>
                                    <p class="text-[0.875rem] font-semibold">{{ __('Des personnes ou des mineurs sont identifiables : un floutage est-il nécessaire ?') }}</p>
                                    <div class="mt-3 flex gap-2">
                                        @foreach (['1' => __('Oui, flouter'), '0' => __('Non')] as $value => $label)
                                            <label @class(['press flex-1 cursor-pointer rounded-full px-4 py-2 text-center text-[0.875rem] font-medium ring-1 transition', 'bg-ink text-canvas ring-ink' => $blur === (string) $value, 'bg-surface ring-line' => $blur !== (string) $value])>
                                                <input type="radio" wire:model.live="blur" value="{{ $value }}" class="sr-only">{{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        @elseif ($sheet === 'reject')
                            <x-console.group :title="__('Motif')">
                                <ul class="max-h-64 overflow-y-auto">
                                    @foreach ($this->reasons as $option)
                                        <li>
                                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem] hover:bg-black/[0.02]">
                                                <input type="radio" wire:model.live="reason" value="{{ $option->uuid }}" class="sr-only">
                                                <span class="flex-1">{{ $option->label }}</span>
                                                @if ($reason === $option->uuid)<x-ui.icon name="check" class="size-4 text-tile-blue" stroke="3" />@endif
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-console.group>
                        @endif

                        @if ($sheet !== '')
                            <div>
                                <label for="decision-comment" class="block text-[0.8125rem] font-medium">{{ $sheet === 'validate' ? __('Commentaire (facultatif)') : __('Commentaire') }}</label>
                                <textarea id="decision-comment" wire:model="comment" rows="3" class="{{ $textarea }}"></textarea>
                            </div>
                        @endif

                        @error('decision') <p class="text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>

                    <footer class="flex items-center justify-end gap-2 border-t border-line px-6 py-4">
                        <x-ui.button variant="secondary" x-on:click="open = ''">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="submit" :variant="$sheet === 'reject' ? 'danger' : ($sheet === 'validate' ? 'success' : 'primary')" wire:loading.attr="disabled" wire:target="decide">
                            @switch($sheet)
                                @case('validate') {{ __('Valider') }} @break
                                @case('reject') {{ __('Rejeter') }} @break
                                @case('complement') {{ __('Envoyer la demande') }} @break
                                @default {{ __('Transmettre') }}
                            @endswitch
                        </x-ui.button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    {{-- Feuille éditoriale --}}
    <div x-data="{ open: $wire.entangle('editorial') }" x-cloak>
        <div x-show="open !== ''" x-transition.opacity.duration.300ms class="fixed inset-0 z-[55] bg-black/30 backdrop-blur-[3px]" x-on:click="open = ''"></div>
        <div x-show="open !== ''" x-on:keydown.escape.window="open = ''" class="fixed inset-0 z-[56] flex items-end justify-center sm:items-center sm:p-6" x-on:click.self="open = ''">
            <div x-show="open !== ''" x-transition:enter="transition duration-500 ease-spring" x-transition:enter-start="opacity-0 translate-y-16 sm:translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[1.6rem] bg-canvas shadow-[0_40px_100px_-30px_rgb(0_0_0/0.5)] sm:max-w-lg sm:rounded-[1.6rem]" role="dialog" aria-modal="true">
                @if ($editorial === 'derogation')
                    <form method="POST" action="{{ route('console.export.relais', $r) }}" class="flex flex-col" x-on:submit="open = ''">
                        @csrf
                        <header class="px-6 pt-6 pb-3">
                            <h2 class="text-[1.375rem] font-bold tracking-[-0.02em]">{{ __('Exporter par dérogation') }}</h2>
                            <p class="mt-1 text-[0.875rem] text-mute">{{ __('Ce relais n’est pas validé. La dérogation et son motif sont inscrits au journal d’audit (ADM-12).') }}</p>
                        </header>
                        <div class="px-6 pb-6">
                            <label for="derogation" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
                            <textarea id="derogation" name="derogation" required rows="3" class="{{ $textarea }}"></textarea>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-line px-6 py-4">
                            <x-ui.button variant="secondary" x-on:click="open = ''">{{ __('Annuler') }}</x-ui.button>
                            <x-ui.button type="submit" variant="danger">{{ __('Télécharger') }}</x-ui.button>
                        </footer>
                    </form>
                @elseif ($editorial !== '')
                    <form wire:submit="{{ match ($editorial) { 'arbitrate-validate' => 'arbitrate(true)', 'arbitrate-reject' => 'arbitrate(false)', 'select' => 'selectRelais', 'broadcast' => 'markBroadcast', default => 'addToCollection' } }}" class="flex min-h-0 flex-1 flex-col">
                        <header class="px-6 pt-6 pb-3">
                            <h2 class="text-[1.375rem] font-bold tracking-[-0.02em]">
                                {{ match ($editorial) { 'arbitrate-validate' => __('Valider'), 'arbitrate-reject' => __('Rejeter'), 'select' => __('Sélectionner et affecter'), 'broadcast' => __('Marquer diffusé'), default => __('Ajouter à une collection') } }}
                            </h2>
                            @if (str_starts_with($editorial, 'arbitrate'))
                                <p class="mt-1 text-[0.875rem] text-mute">{{ __('Arbitrage de la rédaction : la décision précédente reste dans l’historique.') }}</p>
                            @endif
                        </header>
                        <div class="flex-1 space-y-4 overflow-y-auto px-6 pb-6">
                            @if (str_starts_with($editorial, 'arbitrate'))
                                @if ($editorial === 'arbitrate-validate' && $needsBlur)
                                    <fieldset class="rounded-[1rem] bg-status-pending/12 p-4">
                                        <p class="text-[0.875rem] font-semibold">{{ __('Un floutage est-il nécessaire ?') }}</p>
                                        <div class="mt-3 flex gap-2">
                                            @foreach (['1' => __('Oui, flouter'), '0' => __('Non')] as $value => $label)
                                                <label @class(['press flex-1 cursor-pointer rounded-full px-4 py-2 text-center text-[0.875rem] font-medium ring-1', 'bg-ink text-canvas ring-ink' => $blur === (string) $value, 'bg-surface ring-line' => $blur !== (string) $value])>
                                                    <input type="radio" wire:model.live="blur" value="{{ $value }}" class="sr-only">{{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endif
                                <div>
                                    <label for="editor-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
                                    <textarea id="editor-reason" wire:model="editorReason" rows="3" class="{{ $textarea }}"></textarea>
                                </div>
                            @elseif ($editorial === 'select')
                                <x-console.select :label="__('Journaliste')" name="journalist" wire:model="journalist">
                                    <option value="">{{ __('Non affecté') }}</option>
                                    @foreach ($this->journalists as $person)<option value="{{ $person->uuid }}">{{ $person->name }}</option>@endforeach
                                </x-console.select>
                                <x-console.input :label="__('Émission')" name="programme" wire:model="programme" :placeholder="__('Journal de 20 h')" />
                            @elseif ($editorial === 'broadcast')
                                <x-console.input :label="__('Date et heure de diffusion')" type="datetime-local" name="broadcastAt" wire:model="broadcastAt" />
                                <x-console.input :label="__('Émission')" name="broadcastProgramme" wire:model="broadcastProgramme" />
                                <x-console.input :label="__('Lien (facultatif)')" type="url" name="broadcastUrl" wire:model="broadcastUrl" placeholder="https://" />
                            @else
                                <x-console.select :label="__('Collection existante')" name="collection" wire:model="collection">
                                    <option value="">{{ __('Choisir') }}</option>
                                    @foreach ($this->collectionsList as $item)<option value="{{ $item->uuid }}">{{ $item->name }}</option>@endforeach
                                </x-console.select>
                                <x-console.input :label="__('Ou nouvelle collection')" name="newCollection" wire:model="newCollection" :placeholder="__('Inondations de juin')" />
                            @endif
                            @error('editorial') <p class="text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-line px-6 py-4">
                            <x-ui.button variant="secondary" x-on:click="open = ''">{{ __('Annuler') }}</x-ui.button>
                            <x-ui.button type="submit" :variant="$editorial === 'arbitrate-reject' ? 'danger' : 'primary'" wire:loading.attr="disabled">{{ __('Confirmer') }}</x-ui.button>
                        </footer>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
