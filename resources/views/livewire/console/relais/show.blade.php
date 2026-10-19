@php
    use App\Domain\Access\Enums\Permission;
    use App\Domain\Relais\Enums\RelaisStatus;

    $p = $profile;
    $statusTone = ['active' => 'success', 'suspended' => 'warning', 'struck_off' => 'danger'];
    $canManage = auth()->user()->can(Permission::RelaisProfilesSuspend->value);
    $isAdmin = auth()->user()->can(Permission::TerritoireManage->value);
    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
@endphp

<div class="space-y-8">
    <a href="{{ route('console.relais') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Relais') }}
    </a>

    <header class="flex flex-wrap items-center gap-5" data-enter>
        <span class="grid size-20 place-items-center rounded-full bg-relais-500 text-[1.75rem] font-semibold text-white">{{ Str::upper(Str::substr($p->display_name, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[clamp(1.75rem,3.5vw,2.5rem)] leading-tight font-bold tracking-[-0.03em]">{{ $p->candidature?->fullName() ?: $p->display_name }}</h1>
                <x-console.badge :tone="$statusTone[$p->status->value]">{{ $p->status->label() }}</x-console.badge>
            </div>
            <p class="mt-1 text-[0.9375rem] text-mute"><span class="font-mono">{{ $p->number }}</span> · {{ collect([$p->commune?->name, $p->localite?->name])->filter()->join(' · ') }} · {{ __('Relais depuis le :date', ['date' => $p->activated_at->translatedFormat('j F Y')]) }}</p>
        </div>
        @if ($canManage)
            <div class="flex flex-wrap gap-2">
                @if ($p->status === RelaisStatus::Active)
                    <x-ui.button variant="secondary" wire:click="open('suspended')">{{ __('Suspendre') }}</x-ui.button>
                    <x-ui.button variant="ghost" class="text-status-rejected!" wire:click="open('struck_off')">{{ __('Radier') }}</x-ui.button>
                @elseif ($p->status === RelaisStatus::Suspended || $isAdmin)
                    <x-ui.button variant="success" wire:click="open('active')">{{ __('Réintégrer') }}</x-ui.button>
                @endif
                @if ($isAdmin)
                    <x-ui.button variant="secondary" icon="map-pin" wire:click="open('transfer')">{{ __('Transférer') }}</x-ui.button>
                @endif
            </div>
        @endif
    </header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-bento>
        <x-console.stat :label="__('Envoyés')" value="" :count="$this->stats['sent']" icon="inbox" tint="blue" />
        <x-console.stat :label="__('Validés')" value="" :count="$this->stats['validated']" icon="check" tint="green" />
        <x-console.stat :label="__('Rejetés')" value="" :count="$this->stats['rejected']" icon="x" tint="pink" />
        <x-console.stat :label="__('Diffusés')" value="" :count="$this->stats['broadcast']" icon="signal" tint="brand" />
        <x-console.stat :label="__('Fiabilité')" :value="$this->stats['reliability'] < 0 ? '—' : $this->stats['reliability'].' %'" icon="shield" :tint="$this->stats['reliability'] >= 70 ? 'green' : 'orange'" :hint="__('Indicateur interne')" />
    </div>

    <x-console.group :title="__('Historique des relais')">
        @if ($this->history->isEmpty())
            <x-console.empty icon="inbox" tint="blue" :title="__('Aucun envoi')" :text="__('Les relais envoyés par ce Relais apparaîtront ici.')" />
        @else
            <ul>
                @foreach ($this->history as $relais)
                    <x-console.relais-row :relais="$relais" />
                @endforeach
            </ul>
        @endif
    </x-console.group>

    <x-console.slide-over model="acting"
        :title="match ($action) { 'suspended' => __('Suspendre le Relais'), 'struck_off' => __('Radier le Relais'), 'active' => __('Réintégrer le Relais'), 'transfer' => __('Transférer de commune'), default => '' }"
        :description="$action === 'transfer' ? __('Le Relais rejoint la file du coordonnateur de sa nouvelle commune.') : __('Le motif est inscrit au journal d’audit ; l’administration est informée si la décision vient d’un coordonnateur.')">
        <form id="relais-action" wire:submit="apply" class="space-y-4">
            @if ($action === 'transfer')
                <x-console.select :label="__('Nouvelle commune')" name="targetCommune" wire:model="targetCommune">
                    <option value="">{{ __('Choisir') }}</option>
                    @foreach ($this->communes as $commune)<option value="{{ $commune->uuid }}">{{ $commune->name }}</option>@endforeach
                </x-console.select>
            @endif
            <div>
                <label for="relais-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
                <textarea id="relais-reason" wire:model="reason" rows="3" class="{{ $field }}"></textarea>
                @error('reason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="relais-action" :variant="in_array($action, ['suspended', 'struck_off'], true) ? 'danger' : 'primary'">{{ __('Confirmer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
