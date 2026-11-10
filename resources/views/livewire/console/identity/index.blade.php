@php
    $tone = ['submitted' => 'info', 'verified' => 'success', 'rejected' => 'warning'];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Vérifications d’identité')"
        :subtitle="__('Commune, pièce d’identité, selfie et photo de profil envoyés par les Relais. Une identité vérifiée rend le Relais éligible aux gains.')"
    />

    <div class="grid grid-cols-3 gap-4" data-bento>
        <x-console.stat :label="__('À vérifier')" value="" :count="$this->counts['a-verifier']" icon="inbox" tint="blue" :hint="__('Envoyées par les Relais')" />
        <x-console.stat :label="__('Vérifiées')" value="" :count="$this->counts['verifiees']" icon="check" tint="green" :hint="__('Éligibles aux gains')" />
        <x-console.stat :label="__('À corriger')" value="" :count="$this->counts['a-corriger']" icon="pencil" tint="orange" :hint="__('Chez le Relais')" />
    </div>

    <x-console.group :title="trans_choice(':count dossier|:count dossiers', $this->verifications->total())">
        <x-slot:actions>
            <span wire:loading wire:target="search,filter,nextPage,previousPage" class="size-4 animate-spin rounded-full border-2 border-line border-t-tile-blue"></span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Nom du Relais')" />
            <x-console.segmented property="filter" :current="$filter" :options="['a-verifier' => __('À vérifier'), 'verifiees' => __('Vérifiées'), 'a-corriger' => __('À corriger')]" />
        </div>

        @if ($this->verifications->isEmpty())
            <x-console.empty icon="shield" tint="blue" :title="__('Aucun dossier')" :text="__('Les vérifications envoyées depuis l’application apparaîtront ici.')" />
        @else
            <ul wire:loading.class="opacity-50" wire:target="search,filter" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->verifications as $verification)
                    @php
                        $name = $verification->user?->name ?: __('Sans nom');
                        $initials = Str::of($name)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
                        $date = $verification->status === \App\Domain\Identity\Enums\IdentityStatus::Submitted ? $verification->submitted_at : $verification->decided_at;
                    @endphp
                    <li wire:key="identity-{{ $verification->uuid }}">
                        <a href="{{ route('console.identity.show', $verification) }}" wire:navigate class="group flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-tile-blue text-[0.8125rem] font-semibold text-white">{{ $initials }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.9375rem] font-semibold">{{ $name }}</span>
                                <span class="block truncate text-[0.8125rem] text-mute">
                                    {{ collect([$verification->commune?->name, $verification->localite?->name, $verification->document_type?->label()])->filter()->join(' · ') }}
                                </span>
                            </span>
                            <x-console.badge :tone="$tone[$verification->status->value] ?? 'neutral'" class="hidden sm:inline-flex">{{ $verification->status->label() }}</x-console.badge>
                            <span class="hidden w-24 text-right text-[0.8125rem] text-mute sm:block">{{ $date?->diffForHumans(short: true) }}</span>
                            <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($this->verifications->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->verifications->firstItem(), 'to' => $this->verifications->lastItem(), 'total' => $this->verifications->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->verifications->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->verifications->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>
</div>
