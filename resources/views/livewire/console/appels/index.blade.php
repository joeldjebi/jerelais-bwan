@php
    use App\Domain\AppelRelais\Enums\AppelTarget;

    $statusTone = ['open' => 'success', 'pending_approval' => 'warning', 'closed' => 'neutral'];
    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
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
                                <span class="block truncate text-[0.8125rem] text-mute">{{ $appel->target->label() }} · {{ __('jusqu’au :date', ['date' => $appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('j M, H:i')]) }} · {{ $appel->creator?->name }}</span>
                            </span>
                            <span class="hidden text-right text-[0.8125rem] text-mute tabular-nums sm:block">
                                {{ trans_choice(':count réponse|:count réponses', $appel->responses_count) }} · {{ $appel->recipients_count }} {{ __('Relais') }}
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
        <form id="appel-form" wire:submit="create" class="space-y-5">
            <x-console.input :label="__('Sujet')" name="title" wire:model="title" :placeholder="__('Inondations : l’état des routes')" />
            <div>
                <label for="instructions" class="block text-[0.8125rem] font-medium">{{ __('Consignes') }}</label>
                <textarea id="instructions" wire:model="instructions" rows="3" class="{{ $field }}" placeholder="{{ __('Ce que nous cherchons, ce qu’il faut éviter…') }}"></textarea>
                @error('instructions') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
            <x-console.input :label="__('Date limite')" type="datetime-local" name="deadline" wire:model="deadline" />

            @if ($this->isNational)
                <div>
                    <p class="text-[0.8125rem] font-medium">{{ __('Zone ciblée') }}</p>
                    <x-console.segmented class="mt-2" property="target" :current="$target" :options="collect(AppelTarget::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()" />
                </div>
            @endif

            @if ($target === 'communes' || ! $this->isNational)
                <x-console.group :title="__('Communes')">
                    <ul class="max-h-56 overflow-y-auto">
                        @foreach ($this->communes as $commune)
                            <li><label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem]"><input type="checkbox" wire:model="selectedCommunes" value="{{ $commune->uuid }}" class="ios-check">{{ $commune->name }}</label></li>
                        @endforeach
                    </ul>
                </x-console.group>
                @error('selectedCommunes') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            @elseif ($target === 'regions')
                <x-console.group :title="__('Régions')">
                    <ul class="max-h-56 overflow-y-auto">
                        @foreach ($this->regions as $region)
                            <li><label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem]"><input type="checkbox" wire:model="selectedRegions" value="{{ $region->uuid }}" class="ios-check">{{ $region->name }}</label></li>
                        @endforeach
                    </ul>
                </x-console.group>
            @elseif ($target === 'radius')
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-console.input :label="__('Latitude')" name="lat" wire:model="lat" placeholder="5.3412" />
                    <x-console.input :label="__('Longitude')" name="lng" wire:model="lng" placeholder="-4.0688" />
                    <x-console.input :label="__('Rayon (km)')" name="radiusKm" wire:model="radiusKm" />
                </div>
            @endif

            <x-console.select :label="__('Type de lieu (facultatif)')" name="lieuType" wire:model="lieuType">
                <option value="">{{ __('Tous les Relais de la zone') }}</option>
                @foreach ($this->lieuTypes as $type)<option value="{{ $type->uuid }}">{{ $type->name }}</option>@endforeach
            </x-console.select>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="appel-form" wire:loading.attr="disabled" wire:target="create">{{ __('Publier l’appel') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
