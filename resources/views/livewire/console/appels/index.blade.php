@php
    use App\Domain\AppelRelais\Enums\AppelTarget;

    $statusTone = ['open' => 'success', 'pending_approval' => 'warning', 'closed' => 'neutral'];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Rédaction')"
        :title="__('Appels à relais')"
        :subtitle="$this->isNational ? __('Demandez aux Relais d’une zone de couvrir un sujet : ils sont prévenus et leurs envois remontent en priorité.') : __('Lancez un appel local auprès des Relais de votre commune.')"
    >
        <x-slot:actions>
            <x-ui.button icon="bell" wire:click="openCreate">{{ __('Nouvel appel') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <x-console.group :title="trans_choice(':count appel|:count appels', $this->appels->count())">
        @if ($this->appels->isEmpty())
            <x-console.empty icon="bell" tint="purple" :title="__('Aucun appel')" :text="__('Ciblez une commune, une région ou un rayon autour d’un lieu.')" />
        @else
            <ul>
                @foreach ($this->appels as $appel)
                    <li wire:key="appel-{{ $appel->uuid }}">
                        <a href="{{ route('console.appels.show', $appel) }}" wire:navigate class="group flex items-center gap-3.5 px-4 py-3 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <x-console.tile icon="bell" :tint="$appel->is_local ? 'blue' : 'purple'" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.9375rem] font-semibold">{{ $appel->title }}</span>
                                <span class="block truncate text-[0.8125rem] text-mute">{{ $appel->zoneSummary() }}{{ $appel->lieuType ? ' · '.$appel->lieuType->name : '' }} · {{ __('jusqu’au :date', ['date' => $appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('j M, H:i')]) }} · {{ $appel->creator?->name }}</span>
                            </span>
                            <span class="hidden text-right text-[0.8125rem] text-mute tabular-nums sm:block">
                                {{ trans_choice(':count réponse|:count réponses', $appel->responses_count) }} · <span @class(['font-semibold text-status-rejected' => $appel->recipients_count === 0 && $appel->status->value === 'open'])>{{ trans_choice(':count Relais prévenu|:count Relais prévenus', $appel->recipients_count) }}</span>
                            </span>
                            <x-console.badge :tone="$appel->isOpen() ? 'success' : ($statusTone[$appel->status->value] ?? 'neutral')">{{ $appel->isOpen() || $appel->status->value !== 'open' ? $appel->status->label() : __('Échu') }}</x-console.badge>
                            <svg class="size-4 text-mute/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-console.group>

    <x-console.slide-over model="creating" :wide="true" :title="__('Nouvel appel à relais')" :description="__('Les Relais concernés sont prévenus dès la publication.')">
        @include('livewire.console.appels.partials.form', ['formId' => 'appel-form', 'action' => 'create'])
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="appel-form" wire:loading.attr="disabled" wire:target="create">{{ __('Publier l’appel') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
