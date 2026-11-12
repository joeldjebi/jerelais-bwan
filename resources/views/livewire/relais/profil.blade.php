@php
    use App\Domain\Candidature\Enums\Gender;
    use App\Domain\Identity\Enums\IdentityDocumentType;
    use App\Domain\Identity\Enums\IdentityFileKind;
    use App\Domain\Identity\Enums\IdentityStatus;

    $v = $this->verification;
    $status = $v->status;
    $editable = $status->isEditable();
    $missing = $this->missing();
    $files = $v->files->keyBy(fn ($file) => $file->kind->value);
    $type = IdentityDocumentType::tryFrom($documentType) ?? $v->document_type;
    $kinds = [...($type?->sides() ?? []), IdentityFileKind::Selfie, IdentityFileKind::ProfilePhoto];
    $label = 'block text-[0.8125rem] font-medium';
    $field = 'h-12 w-full rounded-2xl border-0 bg-mist px-4 text-[1rem] ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none disabled:opacity-60';
    $chip = 'press inline-flex cursor-pointer items-center rounded-full px-4 py-2 text-[0.875rem] font-medium ring-1 transition-colors';
    $statusTone = [
        'draft' => ['bg-mist text-graphite', __('À compléter')],
        'submitted' => ['bg-tile-blue/10 text-tile-blue', __('En cours de vérification')],
        'verified' => ['bg-tile-green/10 text-tile-green', __('Identité vérifiée : vous êtes éligible aux commissions')],
        'rejected' => ['bg-tile-orange/10 text-tile-orange', __('À corriger')],
    ];
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    @include('livewire.relais.partials.nav', ['active' => 'profile'])

    @if ($relaisProfile = $this->relaisProfile)
        <section data-enter>
            <x-relais.badge :profile="$relaisProfile" />
        </section>
    @endif

    <section class="rounded-[1.75rem] bg-white p-6 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)] sm:p-9" data-enter>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ __('Mon profil') }}</h1>
                <p class="mt-1.5 max-w-lg text-[0.9375rem] leading-relaxed text-graphite">{{ __('À compléter quand vous le souhaitez : il est nécessaire uniquement pour recevoir vos commissions. Vous pouvez envoyer des relais sans l’avoir rempli.') }}</p>
            </div>
            <span class="rounded-full px-3.5 py-1.5 text-[0.8125rem] font-semibold {{ $statusTone[$status->value][0] }}">{{ $statusTone[$status->value][1] }}</span>
        </div>

        @if ($status === IdentityStatus::Rejected && $v->decision_comment)
            <p class="mt-5 rounded-2xl bg-tile-orange/10 p-4 text-[0.9375rem] leading-relaxed"><strong>{{ __('À corriger :') }}</strong> {{ $v->decision_comment }}</p>
        @endif

        @error('form') <p class="mt-5 rounded-2xl bg-status-rejected/10 p-4 text-[0.875rem] font-medium text-status-rejected">{{ $message }}</p> @enderror

        <form wire:submit="save" class="mt-8 space-y-7">
            <fieldset @disabled(! $editable) class="space-y-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="last_name" class="{{ $label }}">{{ __('Nom') }}</label>
                        <input id="last_name" wire:model="identite.last_name" autocomplete="family-name" class="{{ $field }} mt-1.5">
                        @error('identite.last_name') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="first_names" class="{{ $label }}">{{ __('Prénoms') }}</label>
                        <input id="first_names" wire:model="identite.first_names" autocomplete="given-name" class="{{ $field }} mt-1.5">
                        @error('identite.first_names') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="birth_date" class="{{ $label }}">{{ __('Date de naissance') }}</label>
                        <input id="birth_date" type="date" wire:model="identite.birth_date" max="{{ now()->toDateString() }}" autocomplete="bday" class="{{ $field }} mt-1.5">
                        @error('identite.birth_date') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <fieldset>
                        <legend class="{{ $label }}">{{ __('Sexe') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ([Gender::Female, Gender::Male] as $gender)
                                <label @class([$chip, 'bg-onyx text-white ring-onyx' => $identite['gender'] === $gender->value, 'bg-white text-onyx ring-black/10 hover:ring-black/25' => $identite['gender'] !== $gender->value])>
                                    <input type="radio" wire:model.live="identite.gender" value="{{ $gender->value }}" class="sr-only">{{ $gender->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('identite.gender') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </fieldset>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="commune" class="{{ $label }}">{{ __('Commune') }}</label>
                        <select id="commune" wire:model.live="commune" class="{{ $field }} mt-1.5">
                            <option value="">{{ __('Choisir…') }}</option>
                            @foreach ($this->communes as $option)
                                <option value="{{ $option->uuid }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @error('commune') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="localite" class="{{ $label }}">{{ __('Quartier ou village') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                        <select id="localite" wire:model="localite" class="{{ $field }} mt-1.5" @disabled($this->localites->isEmpty())>
                            <option value="">{{ __('Choisir…') }}</option>
                            @foreach ($this->localites as $option)
                                <option value="{{ $option->uuid }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="document_type" class="{{ $label }}">{{ __('Pièce d’identité') }}</label>
                    <select id="document_type" wire:model.live="documentType" class="{{ $field }} mt-1.5">
                        <option value="">{{ __('Choisir…') }}</option>
                        @foreach (IdentityDocumentType::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }} — {{ count($option->sides()) === 2 ? __('recto et verso') : __('une page') }}</option>
                        @endforeach
                    </select>
                    @error('documentType') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            @if ($editable)
                <div class="flex items-center gap-3">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Enregistrer') }}</x-ui.button>
                    @if ($savedAt) <span class="text-[0.8125rem] text-graphite">{{ __('Enregistré à :time', ['time' => $savedAt]) }}</span> @endif
                </div>
            @endif
        </form>

        {{-- Fichiers --}}
        <div class="mt-9 border-t border-black/[0.06] pt-7">
            <h2 class="text-[1.125rem] font-semibold">{{ __('Pièce, selfie et photo') }}</h2>
            <p class="mt-1 text-[0.875rem] text-graphite">
                {{ $type ? __('Pièce : image ou PDF ; selfie et photo : image. 10 Mo maximum. Chaque fichier est analysé par un antivirus.') : __('Choisissez d’abord votre pièce d’identité, puis enregistrez.') }}
            </p>
            <ul class="mt-5 grid gap-3">
                @foreach ($kinds as $kind)
                    @php
                        $file = $files[$kind->value] ?? null;
                        $accept = implode(',', [...$kind->mimeTypes(), '.heic']);
                        $canUpload = $editable && ($kind->isDocumentSide() ? $v->document_type !== null : true);
                    @endphp
                    <li wire:key="file-{{ $kind->value }}"
                        x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false"
                        @class(['flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl p-3 pr-4', 'bg-mist' => $file, 'border border-dashed border-black/15' => ! $file])>
                        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white">
                            <x-ui.icon :name="$file ? 'check' : ($kind->isDocumentSide() ? 'photo' : 'camera')" @class(['size-5', 'text-tile-green' => $file, 'text-graphite' => ! $file]) />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[0.9375rem] font-semibold">{{ $kind->label() }}</p>
                            <p class="text-[0.8125rem] text-graphite">
                                <span x-show="uploading" x-cloak>{{ __('Envoi en cours…') }}</span>
                                <span x-show="! uploading">{{ $file ? __('Envoyé') : __('À ajouter') }}</span>
                            </p>
                            @error('uploads.'.$kind->value) <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        </div>
                        @if ($canUpload)
                            <div class="ml-auto flex items-center gap-1">
                                <label class="press cursor-pointer rounded-full px-3.5 py-2 text-[0.8125rem] font-semibold {{ $file ? 'text-relais-600 hover:bg-black/[0.04]' : 'bg-relais-500 text-white hover:bg-relais-600' }}">
                                    {{ $file ? __('Remplacer') : __('Ajouter') }}
                                    <input type="file" class="sr-only" wire:model="uploads.{{ $kind->value }}" accept="{{ $accept }}" @if ($kind === IdentityFileKind::Selfie) capture="user" @endif>
                                </label>
                                @if ($file)
                                    <button type="button" wire:click="removeFile('{{ $kind->value }}')" class="press grid size-9 place-items-center rounded-full text-graphite hover:bg-black/[0.04] hover:text-status-rejected" aria-label="{{ __('Retirer') }}">
                                        <x-ui.icon name="x" class="size-4" />
                                    </button>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Envoi --}}
        @if ($editable)
            <div class="mt-8 border-t border-black/[0.06] pt-7">
                @if ($missing !== [])
                    <p class="text-[0.875rem] text-graphite">{{ __('Il manque encore :') }} {{ implode(' · ', $missing) }}</p>
                @endif
                <x-ui.button class="mt-4" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" :disabled="$missing !== []">{{ __('Envoyer pour vérification') }}</x-ui.button>
            </div>
        @endif
    </section>
</div>
