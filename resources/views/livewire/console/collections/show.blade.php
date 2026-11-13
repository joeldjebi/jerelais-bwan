<div class="space-y-8">
    <a href="{{ route('console.collections') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Collections') }}
    </a>

    <x-console.page-header :eyebrow="__('Collection')" :title="$collection->name" :subtitle="$collection->description">
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="openEdit">{{ __('Renommer') }}</x-ui.button>
            <x-ui.button variant="ghost" class="text-status-rejected!" wire:click="$set('confirmingDelete', true)">{{ __('Supprimer') }}</x-ui.button>
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

    <x-console.confirm
        model="confirmingDelete"
        action="destroy"
        :title="__('Supprimer la collection ?')"
        :text="__('Les relais qu’elle contient ne sont pas supprimés : ils restent dans le flux et la validation.')"
        :confirm-label="__('Supprimer')"
    />

    <x-console.slide-over model="editing" :title="__('Renommer la collection')">
        <form id="collection-form" wire:submit="save" class="space-y-6">
            <x-console.group>
                <div class="space-y-4 p-4">
                    <x-console.input :label="__('Nom')" name="name" wire:model="name" maxlength="120" autocomplete="off" />
                    <div>
                        <label for="description" class="block text-[0.8125rem] font-medium">{{ __('Description (facultative)') }}</label>
                        <textarea id="description" wire:model="description" rows="3" maxlength="1000" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
                    </div>
                </div>
            </x-console.group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="collection-form" wire:loading.attr="disabled" wire:target="save">{{ __('Enregistrer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
