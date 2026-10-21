@php
    use App\Domain\Settings\Enums\ReferenceList;
    use App\Domain\Territoire\Enums\LocaliteType;
    use App\Domain\Validation\Enums\RejectionTarget;

    $current = $this->current();
    $labelColumn = $current->labelColumn();
    $icons = ['localites' => 'map-pin', 'lieux' => 'layers', 'categories' => 'inbox', 'motifs' => 'shield'];
    $tints = ['localites' => 'teal', 'lieux' => 'purple', 'categories' => 'blue', 'motifs' => 'pink'];
    $footers = [
        'localites' => __('Quartiers, villages et sous-préfectures proposés aux candidats de la commune et utilisés pour la carte de couverture.'),
        'lieux' => __('Écoles, universités, marchés… utilisés par les candidatures et le ciblage des appels à relais.'),
        'categories' => __('Une catégorie désactivée n’est plus proposée aux Relais ; les relais déjà classés la conservent.'),
        'motifs' => __('Motifs proposés aux coordonnateurs lors d’un rejet. Un motif désactivé reste visible dans l’historique.'),
    ];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Référentiels')"
        :subtitle="__('Listes de référence de la plateforme. Chaque ajout ou modification est enregistré dans le journal d’audit.')"
    />

    <div class="mx-auto max-w-3xl space-y-6">
        @if (count($this->lists) > 1)
            <x-console.segmented property="list" :current="$list" :options="collect($this->lists)->mapWithKeys(fn (ReferenceList $item) => [$item->value => $item->label()])->all()" class="w-full" />
        @endif

        <form wire:submit="add">
            <x-console.group :title="__('Ajouter')">
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @if ($current === ReferenceList::Localites)
                            <x-console.select name="commune" :label="__('Commune')" wire:model.live="commune">
                                @foreach ($this->communeOptions as $option)
                                    <option value="{{ $option->uuid }}">{{ $option->name }}{{ $option->is_active ? '' : ' · '.__('fermée') }}</option>
                                @endforeach
                            </x-console.select>
                            <x-console.select name="localiteType" :label="__('Type')" wire:model="localiteType">
                                @foreach (LocaliteType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </x-console.select>
                        @elseif ($current === ReferenceList::RejectionReasons)
                            <x-console.select name="target" :label="__('S’applique aux')" wire:model="target">
                                <option value="{{ RejectionTarget::Relais->value }}">{{ __('Relais envoyés') }}</option>
                                <option value="{{ RejectionTarget::Candidature->value }}">{{ __('Candidatures') }}</option>
                            </x-console.select>
                        @endif
                        <div @class(['sm:col-span-2' => in_array($current, [ReferenceList::LieuTypes, ReferenceList::Categories], true)])>
                            <x-console.input name="name" :label="$current === ReferenceList::RejectionReasons ? __('Libellé du motif') : __('Nom')" wire:model="name" maxlength="120" autocomplete="off" />
                        </div>
                    </div>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="add">{{ __('Ajouter') }}</x-ui.button>
                </div>
            </x-console.group>
        </form>

        <x-console.group :title="$current->label().' · '.$this->entries->count()" :footer="$footers[$current->value]">
            @if ($this->entries->isEmpty())
                <x-console.empty :icon="$icons[$current->value]" :tint="$tints[$current->value]" :title="__('Aucune entrée')" :text="__('Ajoutez la première avec le formulaire ci-dessus.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->entries as $entry)
                        <li wire:key="entry-{{ $entry->uuid }}" class="flex items-center gap-3.5 px-4 py-2.5">
                            <x-console.tile :icon="$icons[$current->value]" :tint="$tints[$current->value]" />
                            @if ($editing === $entry->uuid)
                                <form wire:submit="saveEdit" class="flex min-w-0 flex-1 items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <input type="text" wire:model="editingName" aria-label="{{ __('Nouveau nom') }}" maxlength="120" autofocus
                                            class="h-9 w-full rounded-[0.6rem] border-0 bg-black/[0.055] px-3 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]">
                                        @error('editingName') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="submit" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Enregistrer') }}</button>
                                    <button type="button" wire:click="cancelEdit" class="text-[0.875rem] text-mute hover:text-ink">{{ __('Annuler') }}</button>
                                </form>
                            @else
                                <div class="min-w-0 flex-1">
                                    <p @class(['truncate text-[0.9375rem] font-medium', 'text-mute line-through decoration-1' => $current->canBeDisabled() && ! $entry->is_active])>{{ $entry->{$labelColumn} }}</p>
                                    <p class="truncate text-[0.75rem] text-mute">
                                        @switch($current)
                                            @case(ReferenceList::Localites) {{ $entry->type->label() }} @break
                                            @case(ReferenceList::LieuTypes) {{ trans_choice(':count lieu|:count lieux', $entry->lieux_count) }} @break
                                            @case(ReferenceList::Categories) {{ $entry->examples ?: $entry->slug }} @break
                                            @case(ReferenceList::RejectionReasons) {{ $entry->applies_to === RejectionTarget::Relais ? __('Relais envoyés') : __('Candidatures') }} @break
                                        @endswitch
                                    </p>
                                </div>
                                <button type="button" wire:click="startEdit('{{ $entry->uuid }}')" class="text-[0.8125rem] font-medium text-tile-blue hover:opacity-80">{{ __('Renommer') }}</button>
                                @if ($current->canBeDisabled())
                                    <x-console.switch :on="$entry->is_active" :label="__('Activer :name', ['name' => $entry->{$labelColumn}])" wire:click="toggle('{{ $entry->uuid }}')" />
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    </div>
</div>
