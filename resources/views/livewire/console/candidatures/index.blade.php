@php
    use App\Domain\Candidature\Enums\CandidatureStatus;

    $statusTone = [
        'submitted' => 'info', 'under_review' => 'accent', 'on_hold' => 'warning',
        'complement_requested' => 'warning', 'accepted' => 'success', 'rejected' => 'danger',
    ];
    $avatarBg = ['submitted' => 'bg-tile-blue', 'under_review' => 'bg-relais-500', 'on_hold' => 'bg-tile-orange', 'complement_requested' => 'bg-tile-purple', 'accepted' => 'bg-tile-green', 'rejected' => 'bg-tile-gray'];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Candidatures')"
        :subtitle="__('Les dossiers des futurs Relais de votre périmètre, du plus ancien au plus récent. Vous pouvez appeler le candidat avant de décider.')"
    />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
        <x-console.stat :label="__('À traiter')" value="" :count="$this->counts['a-traiter']" icon="inbox" tint="pink" :hint="__('Reçues ou en examen')" />
        <x-console.stat :label="__('En attente')" value="" :count="$this->counts['attente']" icon="clock" tint="orange" :hint="__('Décision reportée')" />
        <x-console.stat :label="__('Complément demandé')" value="" :count="$this->counts['complement']" icon="pencil" tint="purple" :hint="__('Chez le candidat')" />
        <x-console.stat :label="__('Décidées')" value="" :count="$this->counts['decidees']" icon="check" tint="green" :hint="__('Acceptées ou refusées')" />
    </div>

    <x-console.group :title="trans_choice(':count dossier|:count dossiers', $this->candidatures->total())">
        <x-slot:actions>
            <span wire:loading wire:target="search,filter,nextPage,previousPage" class="size-4 animate-spin rounded-full border-2 border-line border-t-tile-blue"></span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Nom ou prénoms')" />
            <x-console.segmented property="filter" :current="$filter" :options="['a-traiter' => __('À traiter'), 'attente' => __('En attente'), 'complement' => __('Complément'), 'decidees' => __('Décidées')]" />
        </div>

        @if ($this->candidatures->isEmpty())
            <x-console.empty icon="inbox" tint="pink" :title="$filter === 'a-traiter' ? __('Aucun dossier à traiter') : __('Aucun dossier')" :text="__('Les nouvelles candidatures de votre commune apparaîtront ici dès leur dépôt.')" />
        @else
            <ul wire:loading.class="opacity-50" wire:target="search,filter" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->candidatures as $candidature)
                    @php
                        $name = $candidature->fullName() ?: __('Sans nom');
                        $initials = Str::of($name)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
                        $date = $filter === 'decidees' ? $candidature->decided_at : $candidature->submitted_at;
                    @endphp
                    <li wire:key="cand-{{ $candidature->uuid }}">
                        <a href="{{ route('console.candidatures.show', $candidature) }}" wire:navigate class="group flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full text-[0.8125rem] font-semibold text-white {{ $avatarBg[$candidature->status->value] ?? 'bg-tile-gray' }}">{{ $initials }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2 truncate text-[0.9375rem] font-semibold">
                                    {{ $name }}
                                    @if ($candidature->is_minor)<x-console.badge tone="warning">{{ __('Mineur·e') }}</x-console.badge>@endif
                                </span>
                                <span class="block truncate text-[0.8125rem] text-mute">
                                    {{ collect([$candidature->commune?->name, $candidature->localite?->name])->filter()->join(' · ') }}
                                    @if ($candidature->occupation) · {{ $candidature->occupation->label() }}@endif
                                </span>
                            </span>
                            <x-console.badge :tone="$statusTone[$candidature->status->value] ?? 'neutral'" class="hidden sm:inline-flex">{{ $candidature->status->label() }}</x-console.badge>
                            <span class="hidden w-24 text-right text-[0.8125rem] text-mute sm:block">{{ $date?->diffForHumans(short: true) }}</span>
                            <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($this->candidatures->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->candidatures->firstItem(), 'to' => $this->candidatures->lastItem(), 'total' => $this->candidatures->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->candidatures->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->candidatures->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>
</div>
