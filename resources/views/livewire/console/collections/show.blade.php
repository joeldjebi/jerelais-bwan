<div class="space-y-8">
    <a href="{{ route('console.collections') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Collections') }}
    </a>

    <x-console.page-header :eyebrow="__('Collection')" :title="$collection->name" :subtitle="$collection->description">
        <x-slot:actions>
            @can(\App\Domain\Access\Enums\Permission::MediasDownloadOriginal->value)
                <form method="POST" action="{{ route('console.export.collection', $collection) }}">
                    @csrf
                    <x-ui.button type="submit" icon="layers" :disabled="$this->items->isEmpty()">{{ __('Télécharger le lot (ZIP)') }}</x-ui.button>
                </form>
            @endcan
        </x-slot:actions>
    </x-console.page-header>

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->count())" :footer="__('Seuls les relais validés sont inclus dans le téléchargement.')">
        @if ($this->items->isEmpty())
            <x-console.empty icon="layers" tint="teal" :title="__('Collection vide')" :text="__('Ajoutez des relais depuis leur fiche (bouton « Ajouter à une collection »).')" />
        @else
            <ul>
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais">
                        <button type="button" wire:click="remove('{{ $relais->uuid }}')" class="mr-3 grid size-9 shrink-0 place-items-center rounded-full text-mute hover:bg-black/[0.04] hover:text-status-rejected" aria-label="{{ __('Retirer de la collection') }}">
                            <x-ui.icon name="x" class="size-4" />
                        </button>
                    </x-console.relais-row>
                @endforeach
            </ul>
        @endif
    </x-console.group>
</div>
