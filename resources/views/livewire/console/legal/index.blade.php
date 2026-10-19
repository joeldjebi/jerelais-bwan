@php
    use App\Domain\Legal\Enums\LegalDocumentType;

    $tints = ['charte' => 'orange', 'conditions' => 'blue', 'confidentialite' => 'green'];
    $icons = ['charte' => 'flag', 'conditions' => 'book', 'confidentialite' => 'shield'];
    $nextVersion = ($this->current[$type]->version ?? 0) + 1;
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Textes juridiques')"
        :subtitle="__('Charte du Relais, conditions d’utilisation et politique de confidentialité. Chaque nouvelle version doit être acceptée à nouveau par les candidats et les Relais.')"
    />

    <div class="grid gap-4 md:grid-cols-3" data-bento>
        @foreach (LegalDocumentType::cases() as $legalType)
            @php $document = $this->current[$legalType->value] ?? null; @endphp
            <x-console.widget class="flex flex-col justify-between gap-6" data-enter>
                <div class="flex items-start justify-between gap-3">
                    <x-console.tile :icon="$icons[$legalType->value]" :tint="$tints[$legalType->value]" size="lg" />
                    @if ($document)
                        <x-console.badge tone="success">{{ __('v:version', ['version' => $document->version]) }}</x-console.badge>
                    @else
                        <x-console.badge tone="warning">{{ __('À publier') }}</x-console.badge>
                    @endif
                </div>
                <div>
                    <p class="text-[1.0625rem] font-semibold tracking-[-0.01em]">{{ $legalType->label() }}</p>
                    <p class="mt-0.5 text-[0.8125rem] text-mute">
                        @if ($document)
                            {{ __('Publiée le :date', ['date' => $document->published_at->translatedFormat('j F Y')]) }}
                            · {{ trans_choice(':count acceptation|:count acceptations', $this->acceptances[$document->id] ?? 0) }}
                        @else
                            {{ __('Aucune version publiée') }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button size="sm" wire:click="edit('{{ $legalType->value }}')">{{ __('Nouvelle version') }}</x-ui.button>
                    @if ($document)
                        <x-ui.button size="sm" variant="ghost" :href="route('legal.show', $legalType)" target="_blank" icon-trailing="arrow-up-right">{{ __('Voir') }}</x-ui.button>
                    @endif
                </div>
            </x-console.widget>
        @endforeach
    </div>

    <x-console.group :title="__('Historique des versions')" :footer="__('Les versions précédentes restent consultables : chaque acceptation reste liée à la version lue.')">
        <ul data-stagger>
            @forelse ($this->history as $version)
                <li class="flex items-center gap-3.5 px-4 py-3" wire:key="version-{{ $version->uuid }}">
                    <x-console.tile :icon="$icons[$version->type->value]" :tint="$tints[$version->type->value]" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[0.9375rem] font-semibold">{{ $version->title }}</p>
                        <p class="text-[0.8125rem] text-mute">{{ $version->type->label() }} · {{ __('version :version', ['version' => $version->version]) }}</p>
                    </div>
                    <span class="hidden text-[0.8125rem] text-mute tabular-nums sm:block">{{ $version->published_at->translatedFormat('j M Y, H:i') }}</span>
                    <x-console.badge>{{ $this->acceptances[$version->id] ?? 0 }}</x-console.badge>
                </li>
            @empty
                <x-console.empty icon="book" tint="gray" :title="__('Aucun texte publié')" :text="__('Publiez la charte et les conditions avant d’ouvrir les candidatures.')" />
            @endforelse
        </ul>
    </x-console.group>

    <x-console.slide-over model="editing" :wide="true"
        :title="__('Nouvelle version · :type', ['type' => LegalDocumentType::from($type)->label()])"
        :description="__('Version :version. Le texte en Markdown : titres ##, listes, **gras**.', ['version' => $nextVersion])">
        <form id="legal-form" wire:submit="askPublish" class="space-y-5">
            <x-console.input :label="__('Titre')" name="title" wire:model="title" />

            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[0.8125rem] font-medium">{{ __('Texte') }}</span>
                    <x-console.segmented property="mode" :current="$mode" :options="['edit' => __('Rédaction'), 'preview' => __('Aperçu')]" />
                </div>
                @if ($mode === 'edit')
                    <textarea wire:model="body" rows="16" class="mt-2 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-4 font-mono text-[0.8125rem] leading-relaxed focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
                @else
                    <div class="legal-prose mt-2 max-h-[28rem] overflow-y-auto rounded-[0.8rem] bg-surface p-5 ring-1 ring-line">{{ $this->preview() }}</div>
                @endif
                @error('body') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="legal-form">{{ __('Publier la version :version', ['version' => $nextVersion]) }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.confirm
        model="confirming"
        action="publish"
        tone="primary"
        :title="__('Publier la version :version ?', ['version' => $nextVersion])"
        :text="__('Elle entre en vigueur immédiatement. Les candidats et les Relais devront l’accepter à nouveau.')"
        :confirm-label="__('Publier')"
    />
</div>
