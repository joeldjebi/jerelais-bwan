@php
    use App\Domain\Candidature\Enums\CandidatureStatus;
    use App\Domain\Candidature\Enums\DocumentType;
    use App\Domain\Candidature\Enums\Gender;
    use App\Domain\Candidature\Enums\Occupation;
    use App\Domain\Candidature\Support\StepRules;

    $candidature = $this->candidature;
    $editable = $candidature->status->isEditable();
    $keys = $this->stepKeys();
    $index = (int) array_search($step, $keys, true);
    $isRecap = $step === \App\Livewire\Relais\Dossier::RECAP;

    $meta = [
        'identite' => ['users', __('Qui êtes-vous ?'), __('Ces informations restent confidentielles : seuls votre coordonnateur et l’administration y ont accès.')],
        'territoire' => ['map-pin', __('Où êtes-vous ?'), __('Votre candidature est examinée par le coordonnateur de cette commune.')],
        'profil' => ['sparkles', __('Ce qui vous anime'), __('Aidez votre coordonnateur à mieux vous connaître.')],
        'documents' => ['shield', __('Vos pièces justificatives'), __('Protégées et stockées à part : seuls votre coordonnateur et l’administration habilitée peuvent les consulter, et chaque consultation est enregistrée.')],
        'charte' => ['flag', __('La charte du Relais'), __('Lisez attentivement ces engagements : ils protègent les personnes filmées, la chaîne et vous-même.')],
        'formation' => ['book', __('Devenir un bon Relais'), __('Quatre fiches à lire en quelques minutes, puis un court quiz pour vérifier les bons réflexes.')],
        'recapitulatif' => ['check', __('Vérifiez et déposez'), __('Relisez votre dossier. Une fois déposé, il ne sera plus modifiable pendant l’examen.')],
    ];
    $stepLabels = [...collect($this->checklist)->map(fn ($item) => $item['label'])->all(), 'recapitulatif' => __('Dépôt')];

    $field = 'block h-12 w-full rounded-xl border-0 bg-mist px-4 text-[0.9375rem] text-onyx ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 placeholder:text-graphite/60 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none aria-invalid:ring-2 aria-invalid:ring-status-rejected/40';
    $label = 'block text-[0.8125rem] font-medium text-onyx';
    $chip = 'press inline-flex h-10 cursor-pointer items-center gap-2 rounded-full px-4 text-[0.875rem] font-medium ring-1 transition-[background-color,color,box-shadow] duration-300 select-none';
@endphp

<div class="mx-auto max-w-[1080px]">
    @if ($candidature->status === CandidatureStatus::Accepted && $this->relaisProfile)
        <div class="mx-auto mb-8 max-w-3xl">@include('livewire.relais.partials.nav', ['active' => 'badge'])</div>
    @endif
    @if (! $editable)
        {{-- Suivi de la candidature (MOB-06) --}}
        @php
            $status = $candidature->status;
            $timeline = [
                [__('Reçue'), $candidature->submitted_at?->translatedFormat('j F Y à H:i'), true],
                [__('En cours d’examen'), $status === CandidatureStatus::OnHold ? __('Mise en attente par votre coordonnateur') : __('Votre coordonnateur étudie votre dossier'), $status !== CandidatureStatus::Submitted],
                [__('Décision'), $status->isFinal() ? $status->label() : __('Vous serez prévenu·e par SMS'), $status->isFinal()],
            ];
        @endphp
        <div class="mx-auto max-w-xl text-center">
            <div class="mx-auto grid size-24 place-items-center" data-done-badge>
                <svg viewBox="0 0 96 96" class="size-24" aria-hidden="true">
                    <circle cx="48" cy="48" r="44" fill="none" stroke="currentColor" stroke-width="4" class="text-black/[0.06]" />
                    <circle cx="48" cy="48" r="44" fill="none" stroke-width="4" stroke-linecap="round" transform="rotate(-90 48 48)" data-done-ring
                        @class(['stroke-tile-green' => $status !== CandidatureStatus::Rejected, 'stroke-status-rejected' => $status === CandidatureStatus::Rejected]) />
                    @if ($status === CandidatureStatus::Rejected)
                        <path d="M36 36l24 24M60 36L36 60" fill="none" stroke-width="5" stroke-linecap="round" class="stroke-status-rejected" data-done-check />
                    @else
                        <path d="M33 49.5l10 10L64 38" fill="none" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" class="stroke-tile-green" data-done-check />
                    @endif
                </svg>
            </div>

            <h1 class="mt-7 text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]" data-enter>
                @switch($status)
                    @case(CandidatureStatus::Accepted) {{ __('Bienvenue parmi les Relais.') }} @break
                    @case(CandidatureStatus::Rejected) {{ __('Votre candidature n’a pas été retenue.') }} @break
                    @default {{ __('Candidature déposée.') }}
                @endswitch
            </h1>
            <p class="mt-3 text-lg leading-relaxed text-graphite" data-enter>
                @switch($status)
                    @case(CandidatureStatus::Accepted) {{ __('Voici votre badge. Vous pouvez désormais envoyer vos premiers relais depuis l’application.') }} @break
                    @case(CandidatureStatus::Rejected) {{ __('Vous trouverez ci-dessous le motif indiqué par votre coordonnateur.') }} @break
                    @default {{ __('Le coordonnateur de :commune va examiner votre dossier. Il peut vous appeler pour faire connaissance.', ['commune' => $candidature->commune?->name]) }}
                @endswitch
            </p>
        </div>

        @if ($status === CandidatureStatus::Accepted && ($relaisProfile = $this->relaisProfile))
            <div class="mt-12" data-enter>
                <x-relais.badge :profile="$relaisProfile" />
                <p class="mx-auto mt-4 max-w-sm text-center text-[0.8125rem] leading-relaxed text-graphite">{{ __('Présentez ce badge si l’on vous demande qui vous êtes. Le QR code permet de vérifier qu’il est authentique.') }}</p>
            </div>
        @endif

        <div class="mx-auto mt-12 max-w-xl rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)] sm:p-8" data-enter>
            <p class="text-[0.8125rem] font-medium text-graphite">{{ __('Suivi') }}</p>
            <ol class="mt-5">
                @foreach ($timeline as $position => [$title, $text, $done])
                    <li class="relative flex gap-4 pb-7 last:pb-0">
                        @unless ($loop->last)
                            <span @class(['absolute top-8 bottom-1 left-[0.9375rem] w-0.5 rounded-full', 'bg-tile-green' => $timeline[$position + 1][2], 'bg-black/[0.08]' => ! $timeline[$position + 1][2]])></span>
                        @endunless
                        <span @class([
                            'relative grid size-8 shrink-0 place-items-center rounded-full',
                            'bg-tile-green text-white' => $done,
                            'bg-mist text-graphite ring-1 ring-black/[0.06]' => ! $done,
                        ])>
                            @if ($done)
                                <x-ui.icon name="check" class="size-4" stroke="3" />
                            @else
                                <span class="size-2 rounded-full bg-current"></span>
                            @endif
                        </span>
                        <div class="pt-1">
                            <p class="text-[1rem] font-semibold">{{ $title }}</p>
                            <p class="mt-0.5 text-[0.875rem] text-graphite">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($status === CandidatureStatus::Rejected && ($candidature->rejectionReason || $candidature->decision_comment))
                <div class="mt-7 rounded-2xl bg-mist p-5">
                    <p class="text-[0.9375rem] font-semibold">{{ $candidature->rejectionReason?->label }}</p>
                    @if ($candidature->decision_comment)
                        <p class="mt-1 text-[0.9375rem] leading-relaxed text-graphite">{{ $candidature->decision_comment }}</p>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="grid gap-10 lg:grid-cols-[15rem_1fr] lg:gap-14">
            {{-- Étapes --}}
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <h1 class="text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]" data-enter>{{ __('Ma candidature') }}</h1>

                @if ($candidature->status === CandidatureStatus::ComplementRequested)
                    <div class="mt-5 rounded-2xl bg-tile-orange/10 p-4 text-[0.875rem] leading-relaxed text-onyx" data-enter>
                        <p class="font-semibold text-relais-700">{{ __('Complément demandé') }}</p>
                        <p class="mt-1">{{ $candidature->decision_comment }}</p>
                    </div>
                @endif

                {{-- Mobile : barre de progression --}}
                <div class="mt-5 lg:hidden" data-enter>
                    <div class="flex items-center justify-between text-[0.8125rem] text-graphite">
                        <span>{{ __('Étape :current sur :total', ['current' => $index + 1, 'total' => count($keys)]) }}</span>
                        <span class="font-medium text-onyx">{{ $stepLabels[$step] }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/[0.07]">
                        <div class="h-full rounded-full bg-relais-500 transition-[width] duration-700 ease-out-expo" style="width: {{ round(($index + 1) / count($keys) * 100) }}%"></div>
                    </div>
                </div>

                <nav class="mt-7 hidden lg:block" aria-label="{{ __('Étapes de la candidature') }}" data-enter>
                    <ol class="space-y-1">
                        @foreach ($keys as $position => $key)
                            @php
                                $complete = $this->checklist[$key]['complete'] ?? false;
                                $current = $key === $step;
                            @endphp
                            <li>
                                <button type="button" wire:click="goTo('{{ $key }}')" @class([
                                    'group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[0.9375rem] transition-colors duration-300',
                                    'bg-white font-semibold shadow-[0_1px_2px_rgb(0_0_0/0.05)]' => $current,
                                    'text-graphite hover:bg-black/[0.04] hover:text-onyx' => ! $current,
                                ]) @if ($current) aria-current="step" @endif>
                                    <span @class([
                                        'grid size-7 shrink-0 place-items-center rounded-full text-[0.75rem] font-semibold tabular-nums transition-colors duration-300',
                                        'bg-tile-green text-white' => $complete,
                                        'bg-relais-500 text-white' => ! $complete && $current,
                                        'bg-black/[0.06] text-graphite' => ! $complete && ! $current,
                                    ])>
                                        @if ($complete)
                                            <x-ui.icon name="check" class="size-3.5" stroke="3" />
                                        @else
                                            {{ $position + 1 }}
                                        @endif
                                    </span>
                                    {{ $stepLabels[$key] }}
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <p class="mt-6 flex items-center gap-2 text-[0.8125rem] text-graphite" aria-live="polite" data-enter>
                    <span wire:loading.delay wire:target="identite,territoire,profil,uploads" class="inline-flex items-center gap-2">
                        <span class="size-3.5 animate-spin rounded-full border-2 border-black/15 border-t-relais-500"></span>{{ __('Enregistrement…') }}
                    </span>
                    <span wire:loading.remove wire:target="identite,territoire,profil,uploads" class="inline-flex items-center gap-2">
                        <x-ui.icon name="cloud-check" class="size-4 text-tile-green" />
                        {{ $savedAt ? __('Enregistré à :time', ['time' => $savedAt]) : __('Enregistrement automatique') }}
                    </span>
                </p>
            </aside>

            {{-- Étape courante --}}
            <section class="rounded-[1.75rem] bg-white p-5 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)] sm:p-10" data-step-panel wire:key="panel-{{ $step }}" data-enter>
                <header data-field>
                    <span class="grid size-12 place-items-center rounded-2xl bg-relais-500 text-white shadow-[0_10px_24px_-10px_rgb(242_140_40/0.8)]">
                        <x-ui.icon :name="$meta[$step][0]" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[clamp(1.75rem,3.5vw,2.25rem)] leading-tight font-semibold tracking-[-0.035em]">{{ $meta[$step][1] }}</h2>
                    <p class="mt-2 max-w-xl text-[0.9375rem] leading-relaxed text-graphite">{{ $meta[$step][2] }}</p>
                </header>

                <div class="mt-9 space-y-6">
                    @switch($step)
                        @case('identite')
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div data-field>
                                    <label for="last_name" class="{{ $label }}">{{ __('Nom') }}</label>
                                    <input id="last_name" wire:model.live.debounce.700ms="identite.last_name" autocomplete="family-name" class="{{ $field }} mt-1.5" @error('identite.last_name') aria-invalid="true" @enderror>
                                    @error('identite.last_name') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                </div>
                                <div data-field>
                                    <label for="first_names" class="{{ $label }}">{{ __('Prénoms') }}</label>
                                    <input id="first_names" wire:model.live.debounce.700ms="identite.first_names" autocomplete="given-name" class="{{ $field }} mt-1.5" @error('identite.first_names') aria-invalid="true" @enderror>
                                    @error('identite.first_names') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div data-field>
                                    <label for="birth_date" class="{{ $label }}">{{ __('Date de naissance') }}</label>
                                    <input id="birth_date" type="date" wire:model.live="identite.birth_date" max="{{ now()->toDateString() }}" autocomplete="bday" class="{{ $field }} mt-1.5" @error('identite.birth_date') aria-invalid="true" @enderror>
                                    @error('identite.birth_date') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                    @if ($candidature->is_minor)
                                        <p class="mt-1.5 text-xs text-graphite">{{ __('Vous êtes mineur·e : une autorisation parentale signée sera demandée avec vos pièces.') }}</p>
                                    @endif
                                </div>
                                <div data-field>
                                    <label for="email" class="{{ $label }}">{{ __('Adresse e-mail') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                                    <input id="email" type="email" wire:model.blur="identite.email" autocomplete="email" class="{{ $field }} mt-1.5" @error('identite.email') aria-invalid="true" @enderror>
                                    @error('identite.email') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <fieldset data-field>
                                <legend class="{{ $label }}">{{ __('Genre') }}</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach (Gender::cases() as $gender)
                                        <label @class([$chip, 'bg-onyx text-white ring-onyx' => $identite['gender'] === $gender->value, 'bg-white text-onyx ring-black/10 hover:ring-black/25' => $identite['gender'] !== $gender->value])>
                                            <input type="radio" wire:model.live="identite.gender" value="{{ $gender->value }}" class="sr-only">
                                            {{ $gender->label() }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('identite.gender') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            </fieldset>
                            @break

                        @case('territoire')
                            <div data-field>
                                <label for="commune" class="{{ $label }}">{{ __('Commune') }}</label>
                                <select id="commune" wire:model.live="territoire.commune" class="{{ $field }} mt-1.5" @error('territoire.commune') aria-invalid="true" @enderror>
                                    <option value="">{{ __('Choisissez votre commune') }}</option>
                                    @foreach ($this->communes->groupBy(fn ($commune) => $commune->region?->name ?? __('Autres')) as $region => $communes)
                                        <optgroup label="{{ $region }}">
                                            @foreach ($communes as $commune)
                                                <option value="{{ $commune->uuid }}">{{ $commune->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('territoire.commune') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            </div>
                            <div data-field>
                                <label for="localite" class="{{ $label }}">{{ __('Quartier ou village') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                                <select id="localite" wire:model.live="territoire.localite" @disabled($territoire['commune'] === '' || $this->localites->isEmpty()) class="{{ $field }} mt-1.5 disabled:opacity-50">
                                    <option value="">{{ $territoire['commune'] === '' ? __('Choisissez d’abord la commune') : ($this->localites->isEmpty() ? __('Aucun quartier enregistré pour cette commune') : __('Choisissez votre quartier')) }}</option>
                                    @foreach ($this->localites as $localite)
                                        <option value="{{ $localite->uuid }}">{{ $localite->name }}</option>
                                    @endforeach
                                </select>
                                @error('territoire.localite') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            </div>
                            <div data-field>
                                <label for="lieu_type" class="{{ $label }}">{{ __('Lieu que vous fréquentez') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                                <select id="lieu_type" wire:model.live="territoire.lieu_type" class="{{ $field }} mt-1.5">
                                    <option value="">{{ __('École, université, marché…') }}</option>
                                    @foreach ($this->lieuTypes as $lieuType)
                                        <option value="{{ $lieuType->uuid }}">{{ $lieuType->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @break

                        @case('profil')
                            <div data-field>
                                <label for="occupation" class="{{ $label }}">{{ __('Profession ou statut') }}</label>
                                <select id="occupation" wire:model.live="profil.occupation" class="{{ $field }} mt-1.5" @error('profil.occupation') aria-invalid="true" @enderror>
                                    <option value="">{{ __('Choisissez') }}</option>
                                    @foreach (Occupation::cases() as $occupation)
                                        <option value="{{ $occupation->value }}">{{ $occupation->label() }}</option>
                                    @endforeach
                                </select>
                                @error('profil.occupation') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            </div>
                            <fieldset data-field>
                                <legend class="{{ $label }}">{{ __('Langues parlées') }}</legend>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach (StepRules::languages() as $value => $name)
                                        @php $on = in_array($value, $profil['languages'], true); @endphp
                                        <label @class([$chip, 'bg-relais-500 text-white ring-relais-500' => $on, 'bg-white text-onyx ring-black/10 hover:ring-black/25' => ! $on])>
                                            <input type="checkbox" wire:model.live="profil.languages" value="{{ $value }}" class="sr-only">
                                            @if ($on)<x-ui.icon name="check" class="size-3.5" stroke="3" />@endif
                                            {{ $name }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('profil.languages') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            </fieldset>
                            @if ($this->categories->isNotEmpty())
                                <fieldset data-field>
                                    <legend class="{{ $label }}">{{ __('Centres d’intérêt') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></legend>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($this->categories as $category)
                                            @php $on = in_array($category->slug, $profil['interests'], true); @endphp
                                            <label @class([$chip, 'bg-onyx text-white ring-onyx' => $on, 'bg-white text-onyx ring-black/10 hover:ring-black/25' => ! $on])>
                                                <input type="checkbox" wire:model.live="profil.interests" value="{{ $category->slug }}" class="sr-only">
                                                {{ $category->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                            <div data-field x-data="{ count: @js(mb_strlen($profil['motivation'])) }">
                                <label for="motivation" class="{{ $label }}">{{ __('Pourquoi voulez-vous devenir Relais ?') }}</label>
                                <textarea id="motivation" rows="5" maxlength="1500" wire:model.live.debounce.1000ms="profil.motivation" x-on:input="count = $el.value.length"
                                    class="{{ $field }} mt-1.5 h-auto py-3 leading-relaxed" placeholder="{{ __('Ce que vous voyez autour de vous, ce que vous aimeriez montrer…') }}" @error('profil.motivation') aria-invalid="true" @enderror></textarea>
                                <div class="mt-1.5 flex justify-between text-xs">
                                    <span class="font-medium text-status-rejected">@error('profil.motivation'){{ $message }}@enderror</span>
                                    <span class="text-graphite tabular-nums" :class="count < 30 && 'text-relais-600'"><span x-text="count"></span> / 1500</span>
                                </div>
                            </div>
                            @break

                        @case('documents')
                            <ul class="grid gap-3" data-field>
                                @foreach (DocumentType::requiredFor($candidature) as $type)
                                    @php
                                        $document = $candidature->documents->firstWhere('type', $type);
                                        $accept = implode(',', [...$type->mimeTypes(), '.heic']);
                                    @endphp
                                    <li wire:key="doc-{{ $type->value }}"
                                        x-data="{ progress: 0, uploading: false }"
                                        x-on:livewire-upload-start="uploading = true; progress = 0"
                                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                                        x-on:livewire-upload-finish="uploading = false"
                                        x-on:livewire-upload-error="uploading = false"
                                        @class([
                                            'relative flex flex-wrap items-center gap-x-4 gap-y-2 overflow-hidden rounded-2xl p-3 pr-4 transition-colors duration-300',
                                            'bg-mist' => $document,
                                            'border border-dashed border-black/15 bg-white' => ! $document,
                                        ])>
                                        <span class="relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-mist sm:size-[4.5rem]">
                                            @if ($document && $document->isImage() && $document->mime !== 'image/heic' && $document->mime !== 'image/heif')
                                                <img src="{{ route('relais.documents.show', $document) }}" alt="" class="size-full object-cover" loading="lazy">
                                            @elseif ($document)
                                                <x-ui.icon name="check" class="size-6 text-tile-green" stroke="2.5" />
                                            @else
                                                <x-ui.icon :name="$type === DocumentType::Selfie || $type === DocumentType::ProfilePhoto ? 'camera' : 'photo'" class="size-6 text-graphite" />
                                            @endif
                                            <span x-show="uploading" x-cloak class="absolute inset-0 grid place-items-center bg-white/80">
                                                <svg viewBox="0 0 36 36" class="size-9 -rotate-90" aria-hidden="true">
                                                    <circle cx="18" cy="18" r="15" fill="none" stroke-width="3" class="stroke-black/10" />
                                                    <circle cx="18" cy="18" r="15" fill="none" stroke-width="3" stroke-linecap="round" class="stroke-relais-500 transition-[stroke-dashoffset] duration-200" stroke-dasharray="94.25" :stroke-dashoffset="94.25 - 94.25 * progress / 100" />
                                                </svg>
                                            </span>
                                        </span>

                                        <div class="min-w-0 flex-1 basis-[11rem]">
                                            <p class="flex items-center gap-2 text-[0.9375rem] font-semibold">
                                                {{ $type->label() }}
                                                @if ($document)
                                                    <span class="grid size-4.5 place-items-center rounded-full bg-tile-green text-white"><x-ui.icon name="check" class="size-3" stroke="3.5" /></span>
                                                @endif
                                            </p>
                                            <p class="mt-0.5 text-[0.8125rem] leading-snug text-graphite">{{ $type->hint() }}</p>
                                            @error('uploads.'.$type->value) <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="ml-auto flex shrink-0 items-center gap-1">
                                            <label class="press cursor-pointer rounded-full px-3.5 py-2 text-[0.8125rem] font-semibold transition-colors duration-300 {{ $document ? 'text-relais-600 hover:bg-black/[0.04]' : 'bg-relais-500 text-white hover:bg-relais-600' }}">
                                                {{ $document ? __('Remplacer') : __('Ajouter') }}
                                                <input type="file" class="sr-only" wire:model="uploads.{{ $type->value }}" accept="{{ $accept }}" @if ($type === DocumentType::Selfie) capture="user" @endif>
                                            </label>
                                            @if ($document)
                                                <button type="button" wire:click="removeDocument('{{ $type->value }}')" class="press grid size-9 place-items-center rounded-full text-graphite transition-colors duration-300 hover:bg-black/[0.04] hover:text-status-rejected" aria-label="{{ __('Retirer') }}">
                                                    <x-ui.icon name="x" class="size-4" />
                                                </button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="flex items-start gap-2 text-[0.8125rem] leading-relaxed text-graphite" data-field>
                                <x-ui.icon name="shield" class="mt-0.5 size-4 shrink-0 text-tile-green" />
                                {{ __('Photos JPEG, PNG ou HEIC, 10 Mo maximum. Chaque fichier est analysé par un antivirus avant d’être enregistré.') }}
                            </p>
                            @break

                        @case('charte')
                            @php
                                $legal = $this->legalDocuments;
                                $consents = $this->consents;
                                $allAccepted = $legal !== [] && count($consents) === count($legal);
                            @endphp
                            @if ($charte = $legal['charte'] ?? null)
                                <article class="legal-prose max-h-[26rem] overflow-y-auto rounded-2xl bg-mist p-6 sm:p-7" data-field tabindex="0" aria-label="{{ $charte->title }}">
                                    {{ $charte->html() }}
                                </article>
                            @endif

                            <div class="space-y-2" data-field>
                                @foreach ($legal as $type => $legalDocument)
                                    @if ($type !== 'charte')
                                        <details class="group rounded-2xl bg-mist [&_summary::-webkit-details-marker]:hidden">
                                            <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 text-[0.9375rem] font-semibold">
                                                {{ $legalDocument->title }}
                                                <x-ui.icon name="arrow-right" class="size-4 text-graphite transition-transform duration-300 group-open:rotate-90" />
                                            </summary>
                                            <div class="legal-prose max-h-80 overflow-y-auto px-5 pb-5">{{ $legalDocument->html() }}</div>
                                        </details>
                                    @endif
                                @endforeach
                            </div>

                            @if ($allAccepted)
                                <div class="flex items-start gap-3 rounded-2xl bg-tile-green/10 p-5" data-field>
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-tile-green text-white"><x-ui.icon name="check" class="size-4" stroke="3" /></span>
                                    <div class="text-[0.9375rem]">
                                        <p class="font-semibold">{{ __('Textes acceptés') }}</p>
                                        <ul class="mt-1 space-y-0.5 text-[0.8125rem] text-graphite">
                                            @foreach ($consents as $type => $consent)
                                                <li>{{ $legal[$type]->title }} · {{ __('version :version', ['version' => $legal[$type]->version]) }} · {{ $consent->accepted_at->translatedFormat('j F Y à H:i') }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @else
                                <ul class="divide-y divide-black/[0.06] rounded-2xl ring-1 ring-black/[0.08]" data-field>
                                    @foreach ($legal as $type => $legalDocument)
                                        <li>
                                            <label class="flex cursor-pointer items-center gap-3.5 px-5 py-4 text-[0.9375rem]">
                                                <input type="checkbox" wire:model.live="accepted.{{ $type }}" class="ios-check">
                                                <span class="flex-1">
                                                    <span class="block font-medium">{{ $legalDocument->title }}</span>
                                                    <span class="block text-[0.8125rem] text-graphite">{{ __('J’ai lu et j’accepte la version :version', ['version' => $legalDocument->version]) }}</span>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                                @error('accepted') <p class="text-[0.8125rem] font-medium text-status-rejected" data-field>{{ $message }}</p> @enderror
                                <div data-field>
                                    <x-ui.button wire:click="acceptLegal" wire:loading.attr="disabled" wire:target="acceptLegal" :disabled="in_array(false, array_intersect_key($accepted, $legal), true)">
                                        {{ __('J’accepte et je m’engage') }}
                                    </x-ui.button>
                                </div>
                            @endif
                            @break

                        @case('formation')
                            @php
                                $questions = $this->questions;
                                $attempt = $this->lastAttempt;
                                $passing = (int) app(\App\Domain\Settings\Settings::class)->integer('candidature.quiz_min_score');
                            @endphp

                            @if ($formationView === 'quiz' && $questions->isNotEmpty())
                                @php
                                    $question = $questions[$questionIndex];
                                    $isLast = $questionIndex === $questions->count() - 1;
                                @endphp
                                <div data-field>
                                    <div class="flex items-center justify-between text-[0.8125rem] text-graphite">
                                        <span>{{ __('Question :current sur :total', ['current' => $questionIndex + 1, 'total' => $questions->count()]) }}</span>
                                        <button type="button" wire:click="showFiches" class="font-medium text-relais-600 hover:underline">{{ __('Revoir les fiches') }}</button>
                                    </div>
                                    <div class="mt-2 flex gap-1">
                                        @foreach ($questions as $position => $item)
                                            <span @class(['h-1.5 flex-1 rounded-full transition-colors duration-500', 'bg-relais-500' => isset($quizAnswers[$item->uuid]), 'bg-onyx/70' => $position === $questionIndex && ! isset($quizAnswers[$item->uuid]), 'bg-black/[0.07]' => $position !== $questionIndex && ! isset($quizAnswers[$item->uuid])])></span>
                                        @endforeach
                                    </div>
                                </div>

                                <fieldset wire:key="q-{{ $question->uuid }}" data-field>
                                    <legend class="text-[1.375rem] leading-snug font-semibold tracking-[-0.02em]">{{ $question->prompt }}</legend>
                                    <div class="mt-5 space-y-2.5">
                                        @foreach ($question->choices as $choice)
                                            @php $chosen = ($quizAnswers[$question->uuid] ?? null) === $choice->uuid; @endphp
                                            <button type="button" wire:click="answer('{{ $question->uuid }}', '{{ $choice->uuid }}')" @class([
                                                'press flex w-full items-center gap-4 rounded-2xl px-5 py-4 text-left text-[1rem] transition-[background-color,box-shadow] duration-300',
                                                'bg-relais-500/[0.08] ring-2 ring-relais-500' => $chosen,
                                                'bg-mist ring-1 ring-transparent hover:ring-black/10' => ! $chosen,
                                            ]) aria-pressed="{{ $chosen ? 'true' : 'false' }}">
                                                <span @class(['grid size-6 shrink-0 place-items-center rounded-full transition-colors duration-300', 'bg-relais-500 text-white' => $chosen, 'bg-white ring-1 ring-black/15' => ! $chosen])>
                                                    @if ($chosen)<x-ui.icon name="check" class="size-3.5" stroke="3.5" />@endif
                                                </span>
                                                {{ $choice->label }}
                                            </button>
                                        @endforeach
                                    </div>
                                </fieldset>

                                @error('quiz') <p class="text-[0.8125rem] font-medium text-status-rejected" data-field>{{ $message }}</p> @enderror

                                <div class="flex items-center justify-between gap-3" data-field>
                                    <x-ui.button variant="secondary" wire:click="previousQuestion" :disabled="$questionIndex === 0">{{ __('Question précédente') }}</x-ui.button>
                                    @if ($isLast)
                                        <x-ui.button variant="dark" wire:click="submitQuiz" wire:loading.attr="disabled" wire:target="submitQuiz" :disabled="count($quizAnswers) < $questions->count()">{{ __('Valider mes réponses') }}</x-ui.button>
                                    @else
                                        <x-ui.button variant="dark" wire:click="nextQuestion" :disabled="! isset($quizAnswers[$question->uuid])">{{ __('Question suivante') }}</x-ui.button>
                                    @endif
                                </div>
                            @elseif ($formationView === 'resultat' && $attempt)
                                @php
                                    $circumference = 2 * M_PI * 52;
                                    $correction = $questions->keyBy('uuid');
                                @endphp
                                <div class="flex flex-col items-center gap-5 rounded-2xl bg-mist p-7 text-center sm:flex-row sm:text-left" data-field>
                                    <div class="relative grid size-32 shrink-0 place-items-center" data-done-badge>
                                        <svg viewBox="0 0 120 120" class="absolute inset-0 size-32 -rotate-90" aria-hidden="true">
                                            <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" class="stroke-black/[0.07]" />
                                            <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round" @class(['stroke-tile-green' => $attempt->passed, 'stroke-relais-500' => ! $attempt->passed])
                                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $attempt->score / 100) }}" style="transition: stroke-dashoffset 1.2s cubic-bezier(.16,1,.3,1)" />
                                        </svg>
                                        <span class="text-[2rem] font-semibold tracking-[-0.03em] tabular-nums">{{ $attempt->score }}<span class="text-[1.125rem]">%</span></span>
                                    </div>
                                    <div>
                                        <p class="text-[1.375rem] font-semibold tracking-[-0.02em]">{{ $attempt->passed ? __('Quiz réussi, bravo !') : __('Encore un effort.') }}</p>
                                        <p class="mt-1 text-[0.9375rem] leading-relaxed text-graphite">
                                            {{ __(':correct bonnes réponses sur :total. Il faut :passing % pour réussir.', ['correct' => $attempt->correct_count, 'total' => $attempt->question_count, 'passing' => $attempt->passing_score]) }}
                                        </p>
                                        @unless ($attempt->passed)
                                            <x-ui.button size="sm" class="mt-4" wire:click="startQuiz">{{ __('Réessayer') }}</x-ui.button>
                                        @endunless
                                    </div>
                                </div>

                                <ul class="space-y-2" data-field>
                                    @foreach ($attempt->answers as $questionUuid => $result)
                                        @continue(! isset($correction[$questionUuid]))
                                        <li class="flex gap-3 rounded-2xl px-4 py-3.5 ring-1 ring-black/[0.06]">
                                            <span @class(['mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-white', 'bg-tile-green' => $result['correct'], 'bg-status-rejected' => ! $result['correct']])>
                                                <x-ui.icon :name="$result['correct'] ? 'check' : 'x'" class="size-3.5" stroke="3.5" />
                                            </span>
                                            <div>
                                                <p class="text-[0.9375rem] font-semibold">{{ $correction[$questionUuid]->prompt }}</p>
                                                <p class="mt-0.5 text-[0.8125rem] leading-relaxed text-graphite">{{ $correction[$questionUuid]->explanation }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                @if ($attempt && $attempt->passed)
                                    <div class="flex items-center gap-3 rounded-2xl bg-tile-green/10 px-5 py-4" data-field>
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-tile-green text-white"><x-ui.icon name="check" class="size-4" stroke="3" /></span>
                                        <p class="text-[0.9375rem]"><span class="font-semibold">{{ __('Quiz réussi') }}</span> · {{ __('meilleur score :score %', ['score' => \App\Domain\Formation\Actions\GradeQuizAttempt::bestScore(auth()->user())]) }}</p>
                                    </div>
                                @endif

                                <div class="grid items-start gap-3 sm:grid-cols-2" data-field>
                                    @foreach ($this->modules as $module)
                                        <article x-data="{ open: false }" class="rounded-2xl bg-mist p-5" wire:key="module-{{ $module->uuid }}">
                                            <button type="button" x-on:click="open = ! open" class="flex w-full items-start gap-3.5 text-left" :aria-expanded="open">
                                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-relais-500 shadow-[0_1px_2px_rgb(0_0_0/0.05)]"><x-ui.icon :name="$module->icon" class="size-5" /></span>
                                                <span class="flex-1">
                                                    <span class="block text-[1rem] font-semibold">{{ $module->title }}</span>
                                                    <span class="mt-0.5 block text-[0.8125rem] leading-snug text-graphite">{{ $module->summary }}</span>
                                                </span>
                                                <x-ui.icon name="arrow-right" class="mt-1 size-4 shrink-0 text-graphite transition-transform duration-300" ::class="open && 'rotate-90'" />
                                            </button>
                                            <div x-show="open" x-collapse x-cloak class="legal-prose mt-4 border-t border-black/[0.06] pt-4 text-[0.875rem]">{{ $module->html() }}</div>
                                        </article>
                                    @endforeach
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-onyx p-5 text-white sm:p-6" data-field>
                                    <div>
                                        <p class="text-[1.0625rem] font-semibold">{{ __('Le quiz') }}</p>
                                        <p class="text-[0.875rem] text-white/70">{{ __(':count questions · :passing % de bonnes réponses pour réussir', ['count' => $questions->count(), 'passing' => $passing]) }}</p>
                                    </div>
                                    <button type="button" wire:click="startQuiz" class="press rounded-full bg-white px-5 py-2.5 text-[0.9375rem] font-semibold text-onyx transition-colors duration-300 hover:bg-white/90">
                                        {{ $attempt ? __('Repasser le quiz') : __('Commencer le quiz') }}
                                    </button>
                                </div>
                            @endif
                            @break

                        @default
                            <ul class="divide-y divide-black/[0.06] overflow-hidden rounded-2xl bg-mist" data-field>
                                @foreach ($this->checklist as $key => $item)
                                    <li>
                                        <button type="button" wire:click="goTo('{{ $key }}')" class="group flex w-full items-center gap-4 px-5 py-4 text-left transition-colors duration-200 hover:bg-black/[0.025]">
                                            <span @class(['grid size-8 shrink-0 place-items-center rounded-full', 'bg-tile-green text-white' => $item['complete'], 'bg-white text-graphite ring-1 ring-black/10' => ! $item['complete']])>
                                                <x-ui.icon :name="$item['complete'] ? 'check' : 'pencil'" class="size-4" :stroke="$item['complete'] ? '3' : '2'" />
                                            </span>
                                            <span class="flex-1">
                                                <span class="block text-[0.9375rem] font-semibold">{{ $item['label'] }}</span>
                                                <span class="block text-[0.8125rem] text-graphite">
                                                    @switch($key)
                                                        @case('identite') {{ $candidature->fullName() ?: __('À compléter') }}@if ($candidature->birth_date) · {{ $candidature->birth_date->translatedFormat('j F Y') }}@endif @break
                                                        @case('territoire') {{ collect([$candidature->commune?->name, $candidature->localite?->name])->filter()->join(' · ') ?: __('À compléter') }} @break
                                                        @case('profil') {{ $candidature->occupation?->label() ?? __('À compléter') }} @break
                                                        @case('charte') {{ $item['complete'] ? __('Acceptées') : __('À accepter') }} @break
                                                        @case('formation') {{ $item['complete'] ? __('Quiz réussi') : __('Quiz à réussir') }} @break
                                                        @case('documents') {{ __(':count sur :total pièces', ['count' => $candidature->documents->count(), 'total' => count(DocumentType::requiredFor($candidature))]) }} @break
                                                    @endswitch
                                                </span>
                                            </span>
                                            <span class="text-[0.8125rem] font-medium text-relais-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100">{{ __('Modifier') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($missing !== [])
                                <div class="rounded-2xl bg-status-rejected/[0.07] p-5" role="alert" data-field>
                                    <p class="text-[0.9375rem] font-semibold text-status-rejected">{{ __('Votre dossier n’est pas encore complet.') }}</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5 text-[0.875rem] text-onyx">
                                        @foreach ($missing as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <p class="text-[0.8125rem] leading-relaxed text-graphite" data-field>{{ __('En déposant votre dossier, vous certifiez l’exactitude des informations fournies. Vous pourrez suivre son examen depuis cette page et dans l’application.') }}</p>
                    @endswitch
                </div>

                <footer class="mt-10 flex items-center justify-between gap-3 border-t border-black/[0.06] pt-6">
                    @if ($index > 0)
                        <x-ui.button variant="ghost" icon="arrow-left" wire:click="previous">{{ __('Précédent') }}</x-ui.button>
                    @else
                        <span></span>
                    @endif

                    @if ($isRecap)
                        <x-ui.button size="lg" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" icon-trailing="arrow-right">
                            <span wire:loading.remove wire:target="submit">{{ __('Déposer ma candidature') }}</span>
                            <span wire:loading wire:target="submit">{{ __('Dépôt…') }}</span>
                        </x-ui.button>
                    @else
                        <x-ui.button size="lg" wire:click="next" icon-trailing="arrow-right">{{ __('Continuer') }}</x-ui.button>
                    @endif
                </footer>
            </section>
        </div>
    @endif
</div>
