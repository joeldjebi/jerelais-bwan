@php
    use App\Domain\Editorial\Support\CreditLine;

    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Rédaction')"
        :title="__('Diffusions')"
        :subtitle="__('Les relais publiés sur la vitrine « Vu sur REFLET TV » : exactement ce que voient les Relais dans l’application et les visiteurs du site.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-up-right" :href="route('showcase')" target="_blank" rel="noopener">{{ __('Voir la page publique') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <x-console.group :title="trans_choice(':count diffusion|:count diffusions', $this->diffusions->total())" :footer="__('Pour publier un relais : Exploitation → onglet « Diffusés » → ouvrir le relais → « Publier sur la vitrine ».')">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Rechercher un titre ou une émission')" />
        </div>

        @if ($this->diffusions->isEmpty())
            <x-console.empty icon="signal" tint="teal"
                :title="$search !== '' ? __('Aucune diffusion ne correspond') : __('Aucune diffusion pour le moment')"
                :text="$search !== '' ? __('Modifiez la recherche.') : __('Quand la rédaction publie un relais diffusé sur la vitrine, il apparaît ici, dans l’application et sur le site.')" />
        @else
            <ul data-stagger>
                @foreach ($this->diffusions as $relais)
                    <li wire:key="diffusion-{{ $relais->uuid }}" class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-start">
                        @if ($imageUrl = \App\Http\Controllers\Public\ShowcaseController::imageUrl($relais))
                            <img src="{{ $imageUrl }}" alt="" loading="lazy" class="aspect-video w-full shrink-0 rounded-xl object-cover sm:w-44">
                        @else
                            <span class="grid aspect-video w-full shrink-0 place-items-center rounded-xl bg-black/[0.045] text-mute sm:w-44 dark:bg-white/[0.06]"><x-ui.icon name="signal" class="size-6" /></span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="text-[1rem] font-semibold">{{ $relais->showcase_title ?: $relais->title }}</p>
                            @if ($relais->showcase_text)
                                <p class="mt-1 line-clamp-2 text-[0.875rem] text-mute">{{ $relais->showcase_text }}</p>
                            @endif
                            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[0.8125rem] text-mute">
                                <span>{{ collect([$relais->commune?->name, $relais->category?->name])->filter()->join(' · ') }}</span>
                                <span>{{ __('Crédit : :credit', ['credit' => CreditLine::for($relais)]) }}</span>
                                @if ($relais->broadcast_at || $relais->broadcast_programme)
                                    <span>{{ collect([$relais->broadcast_at ? __('Diffusé le :date', ['date' => $date($relais->broadcast_at)]) : null, $relais->broadcast_programme])->filter()->join(' · ') }}</span>
                                @endif
                                <span>{{ __('Sur la vitrine depuis le :date', ['date' => $date($relais->showcased_at)]) }}</span>
                                @if ($relais->broadcast_url)
                                    <a href="{{ $relais->broadcast_url }}" target="_blank" rel="noopener" class="text-tile-blue hover:opacity-80">{{ __('Voir la diffusion') }}</a>
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <x-ui.button size="sm" variant="secondary" :href="route('console.validation.show', $relais)" wire:navigate>{{ __('Ouvrir le relais') }}</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="askWithdraw('{{ $relais->uuid }}')">{{ __('Retirer') }}</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($this->diffusions->hasPages())
                <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->diffusions->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->diffusions->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                </div>
            @endif
        @endif
    </x-console.group>

    <x-console.confirm
        model="confirmingWithdraw"
        action="confirmWithdraw"
        :title="__('Retirer de la vitrine ?')"
        :text="__('Le relais n’apparaîtra plus dans l’application ni sur la page publique. Il reste diffusé et peut être republié depuis sa fiche.')"
        :confirm-label="__('Retirer')"
    />
</div>
