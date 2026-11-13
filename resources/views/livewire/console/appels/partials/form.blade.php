{{-- Formulaire d'un appel à relais (création et modification) : $formId, $action. --}}
@php
    use App\Domain\AppelRelais\Enums\AppelTarget;

    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $audience = $this->audienceCount;
@endphp

<form id="{{ $formId }}" wire:submit="{{ $action }}" class="space-y-5">
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
                    <li wire:key="{{ $formId }}-commune-{{ $commune->uuid }}"><label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem]"><input type="checkbox" wire:model.live="selectedCommunes" value="{{ $commune->uuid }}" class="ios-check">{{ $commune->name }}</label></li>
                @endforeach
            </ul>
        </x-console.group>
        @error('selectedCommunes') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
    @elseif ($target === 'regions')
        <x-console.group :title="__('Régions')">
            <ul class="max-h-56 overflow-y-auto">
                @foreach ($this->regions as $region)
                    <li wire:key="{{ $formId }}-region-{{ $region->uuid }}"><label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem]"><input type="checkbox" wire:model.live="selectedRegions" value="{{ $region->uuid }}" class="ios-check">{{ $region->name }}</label></li>
                @endforeach
            </ul>
        </x-console.group>
        @error('selectedRegions') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
    @elseif ($target === 'radius')
        <div class="grid gap-4 sm:grid-cols-3">
            <x-console.input :label="__('Latitude')" name="lat" wire:model.live.debounce.600ms="lat" placeholder="5.3412" />
            <x-console.input :label="__('Longitude')" name="lng" wire:model.live.debounce.600ms="lng" placeholder="-4.0688" />
            <x-console.input :label="__('Rayon (km)')" name="radiusKm" wire:model.live.debounce.600ms="radiusKm" />
        </div>
    @endif

    <x-console.select :label="__('Type de lieu à couvrir (facultatif)')" name="lieuType" wire:model="lieuType">
        <option value="">{{ __('Aucun en particulier') }}</option>
        @foreach ($this->lieuTypes as $type)<option value="{{ $type->uuid }}">{{ $type->name }}</option>@endforeach
    </x-console.select>
    <p class="-mt-3 text-[0.75rem] text-mute">{{ __('Indiqué aux Relais avec l’appel (école, marché…) ; il ne restreint pas les destinataires.') }}</p>

    <div @class(['flex items-center gap-2 rounded-[0.8rem] px-3.5 py-3 text-[0.875rem]', 'bg-tile-green/10 text-tile-green' => ($audience ?? 0) > 0, 'bg-status-rejected/10 text-status-rejected' => $audience === 0, 'bg-black/[0.045] text-mute' => $audience === null]) wire:loading.class="opacity-60">
        <x-ui.icon name="users" class="size-4" />
        @if ($audience === null)
            {{ __('Complétez la zone pour voir combien de Relais seront prévenus.') }}
        @elseif ($audience === 0)
            {{ __('Aucun Relais dans cette zone pour le moment : l’appel ne sera vu par personne.') }}
        @else
            {{ trans_choice(':count Relais sera prévenu par SMS et verra l’appel dans l’application.|:count Relais seront prévenus par SMS et verront l’appel dans l’application.', $audience) }}
        @endif
    </div>
</form>
