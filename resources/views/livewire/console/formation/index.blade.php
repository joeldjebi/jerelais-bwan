@php
    $textarea = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3 text-[0.9375rem] leading-relaxed focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $moveButton = 'grid size-7 place-items-center rounded-full text-mute transition-colors hover:bg-black/[0.06] hover:text-ink disabled:opacity-25 dark:hover:bg-white/[0.08]';
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Formation et quiz')"
        :subtitle="__('Fiches et quiz proposés aux Relais dans l’application et sur le site. Chaque modification est enregistrée dans le journal d’audit.')"
    >
        <x-slot:actions>
            @if ($tab === 'fiches')
                <x-ui.button wire:click="openModule">{{ __('Nouvelle fiche') }}</x-ui.button>
            @else
                <x-ui.button wire:click="openQuestion">{{ __('Nouvelle question') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-console.page-header>

    <div class="mx-auto max-w-3xl space-y-6">
        <x-console.segmented property="tab" :current="$tab" :options="['fiches' => __('Fiches'), 'quiz' => __('Quiz')]" class="w-full" />

        @if ($tab === 'fiches')
            <x-console.group :title="trans_choice(':count fiche|:count fiches', $this->modules->count())" :footer="__('Les fiches publiées sont proposées dans l’ordre affiché. Une fiche retirée n’est plus visible des Relais.')">
                @if ($this->modules->isEmpty())
                    <x-console.empty icon="book" tint="teal" :title="__('Aucune fiche')" :text="__('Créez la première fiche de formation.')" />
                @else
                    <ul data-stagger>
                        @foreach ($this->modules as $module)
                            <li wire:key="module-{{ $module->uuid }}" class="flex items-center gap-3 px-4 py-3">
                                <div class="flex flex-col">
                                    <button type="button" wire:click="moveModule('{{ $module->uuid }}', -1)" @disabled($loop->first) class="{{ $moveButton }}" aria-label="{{ __('Monter') }}">▲</button>
                                    <button type="button" wire:click="moveModule('{{ $module->uuid }}', 1)" @disabled($loop->last) class="{{ $moveButton }}" aria-label="{{ __('Descendre') }}">▼</button>
                                </div>
                                <x-console.tile :icon="$module->icon" :tint="$module->is_active ? 'teal' : 'gray'" />
                                <div class="min-w-0 flex-1">
                                    <p @class(['truncate text-[0.9375rem] font-medium', 'text-mute line-through decoration-1' => ! $module->is_active])>{{ $module->title }}</p>
                                    <p class="truncate text-[0.75rem] text-mute">{{ $module->summary }}</p>
                                </div>
                                <button type="button" wire:click="openModule('{{ $module->uuid }}')" class="text-[0.8125rem] font-medium text-tile-blue hover:opacity-80">{{ __('Modifier') }}</button>
                                <button type="button" wire:click="askDelete('module', '{{ $module->uuid }}')" class="text-[0.8125rem] font-medium text-status-rejected hover:opacity-80">{{ __('Supprimer') }}</button>
                                <x-console.switch :on="$module->is_active" :label="__('Publier :name', ['name' => $module->title])" wire:click="toggleModule('{{ $module->uuid }}')" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-console.group>
        @else
            <x-console.group :title="trans_choice(':count question|:count questions', $this->questions->count())" :footer="__('Le candidat répond à toutes les questions publiées ; le score minimal se règle dans Paramètres. Une question retirée ne compte plus.')">
                @if ($this->questions->isEmpty())
                    <x-console.empty icon="book" tint="purple" :title="__('Aucune question')" :text="__('Ajoutez la première question du quiz.')" />
                @else
                    <ul data-stagger>
                        @foreach ($this->questions as $question)
                            <li wire:key="question-{{ $question->uuid }}" class="flex items-center gap-3 px-4 py-3">
                                <div class="flex flex-col">
                                    <button type="button" wire:click="moveQuestion('{{ $question->uuid }}', -1)" @disabled($loop->first) class="{{ $moveButton }}" aria-label="{{ __('Monter') }}">▲</button>
                                    <button type="button" wire:click="moveQuestion('{{ $question->uuid }}', 1)" @disabled($loop->last) class="{{ $moveButton }}" aria-label="{{ __('Descendre') }}">▼</button>
                                </div>
                                <x-console.tile icon="book" :tint="$question->is_active ? 'purple' : 'gray'" />
                                <div class="min-w-0 flex-1">
                                    <p @class(['truncate text-[0.9375rem] font-medium', 'text-mute line-through decoration-1' => ! $question->is_active])>{{ $question->prompt }}</p>
                                    <p class="truncate text-[0.75rem] text-mute">
                                        {{ trans_choice(':count réponse|:count réponses', $question->choices->count()) }} · {{ __('bonne réponse :') }} {{ $question->choices->firstWhere('is_correct', true)?->label ?? '—' }}
                                    </p>
                                </div>
                                <button type="button" wire:click="openQuestion('{{ $question->uuid }}')" class="text-[0.8125rem] font-medium text-tile-blue hover:opacity-80">{{ __('Modifier') }}</button>
                                <button type="button" wire:click="askDelete('question', '{{ $question->uuid }}')" class="text-[0.8125rem] font-medium text-status-rejected hover:opacity-80">{{ __('Supprimer') }}</button>
                                <x-console.switch :on="$question->is_active" :label="__('Publier :name', ['name' => $question->prompt])" wire:click="toggleQuestion('{{ $question->uuid }}')" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-console.group>
        @endif
    </div>

    <x-console.confirm
        model="confirmingDelete"
        action="confirmDelete"
        :title="__('Supprimer définitivement ?')"
        :text="__('Cette action est irréversible. Pour masquer temporairement, utilisez plutôt l’interrupteur de publication.')"
        :confirm-label="__('Supprimer')"
    />

    {{-- Fiche de formation --}}
    <x-console.slide-over model="editingModule" wide :title="$moduleUuid ? __('Modifier la fiche') : __('Nouvelle fiche')" :description="__('Le contenu s’écrit en Markdown : **gras**, listes avec « - », titres avec « ## ».')">
        <form id="module-form" wire:submit="saveModule" class="space-y-6">
            <x-console.group>
                <div class="space-y-4 p-4">
                    <x-console.input :label="__('Titre')" name="title" wire:model="title" maxlength="120" autocomplete="off" />
                    <x-console.input :label="__('Résumé (une phrase)')" name="summary" wire:model="summary" maxlength="255" autocomplete="off" />
                    <div>
                        <label for="body" class="block text-[0.8125rem] font-medium">{{ __('Contenu') }}</label>
                        <textarea id="body" wire:model="body" rows="12" class="{{ $textarea }} font-mono text-[0.8125rem]"></textarea>
                        @error('body') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-console.select :label="__('Pictogramme')" name="icon" wire:model="icon">
                            @foreach ($icons as $value => $optionLabel)
                                <option value="{{ $value }}">{{ __($optionLabel) }}</option>
                            @endforeach
                        </x-console.select>
                        <x-console.input :label="__('Vidéo (lien https, facultatif)')" name="videoUrl" type="url" wire:model="videoUrl" maxlength="255" autocomplete="off" />
                    </div>
                </div>
            </x-console.group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="module-form" wire:loading.attr="disabled" wire:target="saveModule">{{ __('Enregistrer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    {{-- Question du quiz --}}
    <x-console.slide-over model="editingQuestion" wide :title="$questionUuid ? __('Modifier la question') : __('Nouvelle question')" :description="__('De :min à :max réponses ; cochez la seule bonne réponse. L’explication s’affiche après la correction.', ['min' => $minChoices, 'max' => $maxChoices])">
        <form id="question-form" wire:submit="saveQuestion" class="space-y-6">
            <x-console.group>
                <div class="space-y-4 p-4">
                    <x-console.input :label="__('Question')" name="prompt" wire:model="prompt" maxlength="255" autocomplete="off" />
                    <fieldset class="space-y-2">
                        <legend class="text-[0.8125rem] font-medium">{{ __('Réponses') }}</legend>
                        @foreach ($choices as $index => $choice)
                            <div wire:key="choice-{{ $index }}" class="flex items-center gap-2">
                                <input type="radio" name="correctChoice" value="{{ $index }}" wire:model="correctChoice" aria-label="{{ __('Bonne réponse') }}" class="size-4 accent-[#30d158]">
                                <input type="text" wire:model="choices.{{ $index }}" maxlength="255" placeholder="{{ __('Réponse :n', ['n' => $index + 1]) }}"
                                    class="h-10 min-w-0 flex-1 rounded-[0.6rem] border-0 bg-black/[0.055] px-3 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]">
                                @if (count($choices) > $minChoices)
                                    <button type="button" wire:click="removeChoice({{ $index }})" class="text-[0.8125rem] text-mute hover:text-status-rejected" aria-label="{{ __('Retirer la réponse') }}">{{ __('Retirer') }}</button>
                                @endif
                            </div>
                            @error('choices.'.$index) <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        @endforeach
                        @error('choices') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        @error('correctChoice') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        @if (count($choices) < $maxChoices)
                            <button type="button" wire:click="addChoice" class="text-[0.8125rem] font-medium text-tile-blue hover:opacity-80">{{ __('+ Ajouter une réponse') }}</button>
                        @endif
                    </fieldset>
                    <div>
                        <label for="explanation" class="block text-[0.8125rem] font-medium">{{ __('Explication') }}</label>
                        <textarea id="explanation" wire:model="explanation" rows="4" class="{{ $textarea }}"></textarea>
                        @error('explanation') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-console.group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="question-form" wire:loading.attr="disabled" wire:target="saveQuestion">{{ __('Enregistrer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
