@php
    $validated = $this->responses->filter(fn ($relais) => $relais->isExploitable())->count();
    $rate = $appel->recipients_count > 0 ? (int) round($this->responses->pluck('user_id')->unique()->count() / $appel->recipients_count * 100) : null;
@endphp

<div class="space-y-8">
    <a href="{{ route('console.appels') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Appels à relais') }}
    </a>

    <x-console.page-header :eyebrow="$appel->is_local ? __('Appel local') : __('Appel de la rédaction')" :title="$appel->title" :subtitle="$appel->instructions">
        <x-slot:actions>
            @if ($pending && auth()->user()->can(\App\Domain\Access\Enums\Permission::SettingsManage->value))
                <x-ui.button variant="success" icon="check" wire:click="approve">{{ __('Approuver et publier') }}</x-ui.button>
            @endif
            @if ($appel->isOpen())
                <x-ui.button variant="secondary" wire:click="close">{{ __('Clore l’appel') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-console.page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
        <x-console.stat :label="__('Relais prévenus')" value="" :count="$appel->recipients_count" icon="users" tint="blue" :hint="$appel->target->label()" />
        <x-console.stat :label="__('Réponses')" value="" :count="$this->responses->count()" icon="inbox" tint="purple" :hint="$rate === null ? __('Aucun destinataire') : __(':rate % des Relais ont répondu', ['rate' => $rate])" />
        <x-console.stat :label="__('Validées')" value="" :count="$validated" icon="check" tint="green" :hint="__('Exploitables par la rédaction')" />
        <x-console.stat :label="__('Date limite')" :value="$appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('j M, H:i')" icon="clock" :tint="$appel->isOpen() ? 'orange' : 'gray'" :hint="$appel->isOpen() ? __('Ouvert') : $appel->status->label()" />
    </div>

    <x-console.group :title="__('Zone')">
        <p class="px-5 py-4 text-[0.9375rem]">
            {{ $appel->target->label() }}
            @if ($appel->communes->isNotEmpty()) : {{ $appel->communes->pluck('name')->join(', ') }}@endif
            @if ($appel->regions->isNotEmpty()) : {{ $appel->regions->pluck('name')->join(', ') }}@endif
            @if ($appel->center) : {{ number_format($appel->radius_m / 1000, 1, ',', ' ') }} km {{ __('autour de') }} {{ number_format($appel->center->getLatitude(), 4) }}, {{ number_format($appel->center->getLongitude(), 4) }}@endif
            @if ($appel->lieuType) · {{ $appel->lieuType->name }}@endif
        </p>
    </x-console.group>

    <x-console.group :title="trans_choice(':count réponse|:count réponses', $this->responses->count())">
        @if ($this->responses->isEmpty())
            <x-console.empty icon="inbox" tint="purple" :title="__('Pas encore de réponse')" :text="__('Les envois des Relais rattachés à cet appel apparaîtront ici.')" />
        @else
            <ul>
                @foreach ($this->responses as $relais)
                    <x-console.relais-row :relais="$relais" />
                @endforeach
            </ul>
        @endif
    </x-console.group>
</div>
