@php
    use App\Domain\Relais\Enums\RelaisStatus;

    $statusTone = ['active' => 'success', 'suspended' => 'warning', 'struck_off' => 'danger'];
@endphp

<div class="space-y-8">
    <x-console.page-header :eyebrow="__('Réseau')" :title="__('Relais')" :subtitle="__('Les Relais recrutés de votre périmètre : activité, fiabilité, statut.')" />

    <x-console.group :title="trans_choice(':count Relais|:count Relais', $this->profiles->total())">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Nom ou numéro')" />
            <x-console.segmented property="status" :current="$status" :options="['' => __('Tous'), 'active' => __('Actifs'), 'suspended' => __('Suspendus'), 'struck_off' => __('Radiés')]" />
        </div>

        @if ($this->profiles->isEmpty())
            <x-console.empty icon="signal" tint="brand" :title="__('Aucun Relais')" :text="__('Les candidats acceptés apparaissent ici.')" />
        @else
            <ul>
                @foreach ($this->profiles as $profile)
                    @php $rate = $profile->sent_count > 0 ? (int) round($profile->validated_count / $profile->sent_count * 100) : null; @endphp
                    <li wire:key="rp-{{ $profile->uuid }}">
                        <a href="{{ route('console.relais.show', $profile) }}" wire:navigate class="group flex items-center gap-3.5 px-4 py-3 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-relais-500 text-[0.8125rem] font-semibold text-white">{{ Str::upper(Str::substr($profile->display_name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.9375rem] font-semibold">{{ $profile->display_name }} <span class="font-mono text-[0.8125rem] font-normal text-mute">{{ $profile->number }}</span></span>
                                <span class="block truncate text-[0.8125rem] text-mute">{{ collect([$profile->commune?->name, $profile->localite?->name])->filter()->join(' · ') }}</span>
                            </span>
                            <span class="hidden text-right text-[0.8125rem] text-mute tabular-nums sm:block">{{ trans_choice(':count envoi|:count envois', $profile->sent_count) }}@if ($rate !== null) · {{ $rate }} % {{ __('validés') }}@endif</span>
                            <x-console.badge :tone="$statusTone[$profile->status->value]">{{ $profile->status->label() }}</x-console.badge>
                            <svg class="size-4 text-mute/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($this->profiles->hasPages())
                <div class="flex justify-end gap-1.5 border-t border-line px-4 py-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->profiles->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->profiles->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                </div>
            @endif
        @endif
    </x-console.group>
</div>
