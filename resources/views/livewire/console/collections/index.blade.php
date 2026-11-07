<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Rédaction')"
        :title="__('Collections')"
        :subtitle="__('Regroupez les relais d’un même sujet ou d’un même événement, puis téléchargez-les en un seul lot.')"
    >
        <x-slot:actions>
            <x-ui.button icon="layers" wire:click="$set('creating', true)">{{ __('Nouvelle collection') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    @if ($this->collections->isEmpty())
        <x-console.group>
            <x-console.empty icon="layers" tint="teal" :title="__('Aucune collection')" :text="__('Créez-en une, par exemple « Inondations de juin », puis ajoutez-y des relais depuis leur fiche.')" />
        </x-console.group>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-bento>
            @foreach ($this->collections as $collection)
                <x-console.widget :href="route('console.collections.show', $collection)" class="flex min-h-40 flex-col justify-between" data-enter wire:key="col-{{ $collection->uuid }}">
                    <x-console.tile icon="layers" tint="teal" size="lg" />
                    <div>
                        <p class="text-[1.0625rem] font-semibold tracking-[-0.01em]">{{ $collection->name }}</p>
                        <p class="text-[0.8125rem] text-mute">{{ trans_choice(':count relais|:count relais', $collection->relais_count) }} · {{ $collection->creator?->name }}</p>
                    </div>
                </x-console.widget>
            @endforeach
        </div>
    @endif

    <x-console.slide-over model="creating" :title="__('Nouvelle collection')" :description="__('Un sujet ou un événement.')">
        <form id="collection-form" wire:submit="create" class="space-y-4">
            <x-console.input :label="__('Nom')" name="name" wire:model="name" :placeholder="__('Inondations de juin')" />
            <div>
                <label for="col-description" class="block text-[0.8125rem] font-medium">{{ __('Description (facultatif)') }}</label>
                <textarea id="col-description" wire:model="description" rows="3" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="collection-form">{{ __('Créer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
