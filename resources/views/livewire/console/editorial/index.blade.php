<div class="space-y-8" wire:poll.10s.keep-alive>
    <x-console.page-header
        :eyebrow="__('Rédaction')"
        :title="__('Exploitation')"
        :subtitle="__('Des relais validés à l’antenne : arbitrez les sujets sensibles, sélectionnez, affectez, téléchargez en qualité d’origine et marquez les diffusions.')"
    />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-bento>
        <x-console.stat :label="__('À arbitrer')" value="" :count="$this->counts['a-arbitrer']" icon="flag" :tint="$this->counts['a-arbitrer'] > 0 ? 'orange' : 'gray'" :hint="__('Sujets sensibles')" />
        <x-console.stat :label="__('À exploiter')" value="" :count="$this->counts['a-exploiter']" icon="check" tint="green" :hint="__('Validés par les coordonnateurs')" />
        <x-console.stat :label="__('Sélectionnés')" value="" :count="$this->counts['selection']" icon="sparkles" tint="purple" :hint="__('En préparation')" />
        <x-console.stat :label="__('Diffusés')" value="" :count="$this->counts['diffuses']" icon="signal" tint="brand" :hint="__('Passés à l’antenne')" />
        <x-console.stat :label="__('Sur la vitrine')" value="" :count="$this->counts['vitrine']" icon="sparkles" tint="teal" :hint="__('Visibles dans l’application')" />
    </div>

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->total())">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.segmented property="tab" :current="$tab" :options="['a-arbitrer' => __('À arbitrer'), 'a-exploiter' => __('À exploiter'), 'selection' => __('Sélection'), 'diffuses' => __('Diffusés'), 'vitrine' => __('Vitrine')]" />
            @if ($tab === 'selection')
                <label class="ml-auto inline-flex items-center gap-2 text-[0.875rem]">
                    <input type="checkbox" wire:model.live="mine" class="ios-check">{{ __('Affectés à moi') }}
                </label>
            @endif
        </div>

        @if ($tab === 'vitrine')
            <p class="border-b border-line px-4 py-3 text-[0.8125rem] text-mute">{{ __('Relais publiés sur la vitrine « Vu sur REFLET TV » : exactement ce que l’application affiche (GET /diffusions) et la page publique du site. Pour en ajouter : bouton « Diffuser » dans l’onglet « À exploiter » ou « Sélection ».') }}</p>
        @endif

        @if ($this->items->isEmpty())
            <x-console.empty icon="sparkles" tint="purple" :title="__('Rien pour l’instant')" :text="$tab === 'vitrine' ? __('Aucun relais publié sur la vitrine. Onglet « À exploiter » → bouton « Diffuser ».') : __('Les relais apparaissent ici dès qu’ils sont validés ou transmis par un coordonnateur.')" />
        @else
            <ul>
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais">
                        @if (in_array($tab, ['a-exploiter', 'selection'], true))
                            <div class="shrink-0 pr-4">
                                <x-ui.button size="sm" variant="success" icon="signal" wire:click="openPublish('{{ $relais->uuid }}')">{{ __('Diffuser') }}</x-ui.button>
                            </div>
                        @endif
                    </x-console.relais-row>
                @endforeach
            </ul>

            @if ($this->items->hasPages())
                <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->items->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->items->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                </div>
            @endif
        @endif
    </x-console.group>

    <x-console.slide-over model="publishOpen" :title="__('Diffuser et publier')" :description="__('Le relais passe « diffusé », apparaît sur la page d’accueil, sur « Vu sur REFLET TV » et dans l’application, et le Relais est prévenu.')">
        @if ($relais = $this->publishing)
            <form id="publish-form" wire:submit="publish" class="space-y-4">
                <p class="text-[0.9375rem] font-semibold">{{ $relais->title ?: __('Sans titre') }}</p>
                <x-console.input :label="__('Date et heure de diffusion')" type="datetime-local" name="broadcastAt" wire:model="broadcastAt" />
                <x-console.input :label="__('Émission')" name="broadcastProgramme" wire:model="broadcastProgramme" :placeholder="__('Journal de 20 h')" />
                <x-console.input :label="__('Lien de la diffusion (facultatif)')" type="url" name="broadcastUrl" wire:model="broadcastUrl" placeholder="https://" />
                <div class="border-t border-line pt-4">
                    <x-console.input :label="__('Titre public')" name="showcaseTitle" wire:model="showcaseTitle" />
                </div>
                <div>
                    <label for="publish-text" class="block text-[0.8125rem] font-medium">{{ __('Texte public (facultatif)') }}</label>
                    <textarea id="publish-text" wire:model="showcaseText" rows="3" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
                </div>
                @if ($relais->blur_required || $relais->has_minors)
                    <p class="rounded-[0.8rem] bg-status-pending/12 px-3 py-2 text-[0.8125rem]">{{ __('Floutage demandé ou mineurs : l’image ne sera pas publiée.') }}</p>
                @else
                    <label class="flex items-center gap-3 text-[0.9375rem]"><input type="checkbox" wire:model="showcaseImage" class="ios-check">{{ __('Publier l’image') }}</label>
                @endif
                @error('publish') <p class="text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
            </form>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="publish-form" variant="success" icon="signal" wire:loading.attr="disabled" wire:target="publish">{{ __('Diffuser et publier') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
