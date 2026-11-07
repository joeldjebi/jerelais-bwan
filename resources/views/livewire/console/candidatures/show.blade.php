@php
    use App\Domain\Candidature\Enums\CandidatureStatus;
    use App\Domain\Candidature\Enums\DocumentType;
    use App\Domain\Candidature\Support\AgeRequirement;
    use App\Domain\Candidature\Support\StepRules;
    use Illuminate\Support\Facades\URL;

    $c = $this->candidature;
    $canDecide = Gate::allows('decide', $c);
    $canSeeIdentity = Gate::allows('viewIdentity', $c);
    $minutes = (int) config('jerelais.candidature.identity_url_minutes');
    $docs = $c->documents->keyBy(fn ($document) => $document->type->value);
    $url = fn (?\App\Domain\Candidature\Models\CandidatureDocument $document) => $document && $canSeeIdentity
        ? URL::temporarySignedRoute('console.candidatures.document', now()->addMinutes($minutes), $document)
        : null;
    $name = $c->fullName() ?: __('Sans nom');
    $initials = Str::of($name)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
    $statusTone = ['submitted' => 'info', 'under_review' => 'accent', 'on_hold' => 'warning', 'complement_requested' => 'warning', 'accepted' => 'success', 'rejected' => 'danger'];
    $languages = StepRules::languages();
    $score = $this->quizScore;
    $passing = (int) app(\App\Domain\Settings\Settings::class)->integer('candidature.quiz_min_score');
    $circumference = 2 * M_PI * 34;
@endphp

<div class="space-y-7" x-data="{ zoom: null }">
    <a href="{{ route('console.candidatures') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Candidatures') }}
    </a>

    {{-- En-tête --}}
    <header class="flex flex-wrap items-center gap-5" data-enter>
        @if ($photo = $url($docs['profile_photo'] ?? null))
            <img src="{{ $photo }}" alt="" class="size-20 rounded-full object-cover shadow-[0_10px_30px_-12px_rgb(0_0_0/0.4)] ring-4 ring-surface">
        @else
            <span class="grid size-20 place-items-center rounded-full bg-relais-500 text-[1.75rem] font-semibold text-white">{{ $initials }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[clamp(1.75rem,3.5vw,2.5rem)] leading-tight font-bold tracking-[-0.03em]">{{ $name }}</h1>
                <x-console.badge :tone="$statusTone[$c->status->value] ?? 'neutral'">{{ $c->status->label() }}</x-console.badge>
                @if ($c->is_minor)<x-console.badge tone="warning">{{ __('Mineur·e') }}</x-console.badge>@endif
            </div>
            <p class="mt-1 text-[0.9375rem] text-mute">
                {{ collect([$c->commune?->name, $c->localite?->name])->filter()->join(' · ') }}
                · {{ __('déposée :when', ['when' => $c->submitted_at?->diffForHumans()]) }}
            </p>
        </div>
        @if ($canDecide)
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="ghost" class="text-status-rejected!" wire:click="openReject">{{ __('Refuser') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="openComplement">{{ __('Demander un complément') }}</x-ui.button>
                @if ($c->status !== CandidatureStatus::OnHold)
                    <x-ui.button variant="secondary" wire:click="$set('confirmingHold', true)">{{ __('Mettre en attente') }}</x-ui.button>
                @endif
                <x-ui.button variant="success" icon="check" wire:click="$set('confirmingAccept', true)">{{ __('Accepter') }}</x-ui.button>
            </div>
        @endif
    </header>

    @if ($c->decided_at && ! $c->status->isPending() || $c->status === CandidatureStatus::OnHold)
        <div @class(['flex items-start gap-3 rounded-[1.2rem] p-4', 'bg-status-validated/10' => $c->status === CandidatureStatus::Accepted, 'bg-status-rejected/10' => $c->status === CandidatureStatus::Rejected, 'bg-status-pending/12' => ! in_array($c->status, [CandidatureStatus::Accepted, CandidatureStatus::Rejected], true)]) data-enter>
            <x-console.tile :icon="$c->status === CandidatureStatus::Accepted ? 'check' : ($c->status === CandidatureStatus::Rejected ? 'x' : 'clock')" :tint="$c->status === CandidatureStatus::Accepted ? 'green' : ($c->status === CandidatureStatus::Rejected ? 'pink' : 'orange')" />
            <div class="text-[0.9375rem]">
                <p class="font-semibold">{{ $c->status->label() }} · {{ $c->decider?->name }} · {{ $c->decided_at?->translatedFormat('j F Y à H:i') }}</p>
                @if ($c->rejectionReason)<p class="mt-0.5">{{ $c->rejectionReason->label }}</p>@endif
                @if ($c->decision_comment)<p class="mt-0.5 text-mute">« {{ $c->decision_comment }} »</p>@endif
            </div>
        </div>
    @endif

    @if ($this->duplicates !== [])
        <div class="rounded-[1.2rem] bg-status-rejected/[0.08] p-4" role="alert" data-enter>
            <div class="flex items-center gap-3">
                <x-console.tile icon="users" tint="pink" />
                <p class="text-[0.9375rem] font-semibold">{{ trans_choice('Doublon possible : :count autre dossier|Doublons possibles : :count autres dossiers', count($this->duplicates)) }}</p>
            </div>
            <ul class="mt-3 space-y-1.5 pl-11 text-[0.875rem]">
                @foreach ($this->duplicates as $match)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-console.badge tone="danger">{{ $match->label() }}</x-console.badge>
                        <span class="font-medium">{{ $match->candidature->fullName() ?: __('Sans nom') }}</span>
                        <span class="text-mute">· {{ $match->candidature->commune?->name ?? __('commune inconnue') }} · {{ $match->candidature->status->label() }}</span>
                        @can('view', $match->candidature)
                            <a href="{{ route('console.candidatures.show', $match->candidature) }}" wire:navigate class="font-medium text-tile-blue hover:opacity-80">{{ __('Ouvrir') }}</a>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_21rem]">
        <div class="space-y-6">
            {{-- Comparaison visuelle (COO-03) --}}
            <x-console.group :title="__('Vérification d’identité')" :footer="$canSeeIdentity ? __('Chaque ouverture d’une pièce est enregistrée dans le journal d’audit. Cliquez pour agrandir.') : __('Les pièces d’identité sont réservées au coordonnateur de la commune et aux administrateurs habilités.')">
                @if ($canSeeIdentity)
                    <div class="grid grid-cols-3 gap-3 p-3">
                        @foreach ([DocumentType::ProfilePhoto, DocumentType::Selfie, DocumentType::IdFront] as $type)
                            @php $src = $url($docs[$type->value] ?? null); @endphp
                            <figure class="min-w-0">
                                @if ($src && ($docs[$type->value]->isImage()))
                                    <button type="button" x-on:click="zoom = @js($src)" class="block aspect-[3/4] w-full overflow-hidden rounded-[0.9rem] bg-black/[0.04] transition-transform duration-500 ease-spring hover:scale-[1.02]">
                                        <img src="{{ $src }}" alt="{{ $type->label() }}" class="size-full object-cover" loading="lazy">
                                    </button>
                                @else
                                    <div class="grid aspect-[3/4] w-full place-items-center rounded-[0.9rem] bg-black/[0.04] text-[0.75rem] text-mute">{{ $src ? __('Format non affichable') : __('Non fournie') }}</div>
                                @endif
                                <figcaption class="mt-1.5 truncate text-center text-[0.75rem] font-medium text-mute">{{ $type->label() }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                    <ul class="border-t border-line">
                        @foreach ([DocumentType::IdBack, DocumentType::ParentalAuthorization] as $type)
                            @continue($type === DocumentType::ParentalAuthorization && ! $c->is_minor)
                            @php $document = $docs[$type->value] ?? null; $src = $url($document); @endphp
                            <li class="flex items-center gap-3.5 px-4 py-3">
                                <x-console.tile :icon="$type === DocumentType::IdBack ? 'photo' : 'book'" :tint="$document ? 'blue' : 'gray'" />
                                <span class="flex-1 text-[0.9375rem]">{{ $type->label() }}</span>
                                @if ($src && $document->isImage())
                                    <button type="button" x-on:click="zoom = @js($src)" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Afficher') }}</button>
                                @elseif ($src)
                                    <a href="{{ $src }}" target="_blank" rel="noopener" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Ouvrir') }}</a>
                                @else
                                    <x-console.badge tone="warning">{{ __('Manquante') }}</x-console.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-console.empty icon="shield" tint="gray" :title="__('Accès restreint')" :text="__('Vous ne pouvez pas consulter les pièces de ce dossier.')" />
                @endif
            </x-console.group>

            <x-console.group :title="__('Motivation')">
                <p class="px-5 py-4 text-[0.9375rem] leading-relaxed whitespace-pre-line">{{ $c->motivation ?: '—' }}</p>
            </x-console.group>
        </div>

        <div class="space-y-6">
            <x-console.widget :tone="$score !== null && $score >= $passing ? 'green' : 'light'" class="flex items-center gap-4" data-enter>
                <div class="relative grid size-20 shrink-0 place-items-center">
                    <svg viewBox="0 0 80 80" class="absolute inset-0 size-20 -rotate-90" aria-hidden="true">
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" class="{{ $score !== null && $score >= $passing ? 'stroke-white/25' : 'stroke-black/[0.07]' }}" />
                        <circle cx="40" cy="40" r="34" fill="none" stroke-width="8" stroke-linecap="round" class="{{ $score !== null && $score >= $passing ? 'stroke-white' : 'stroke-relais-500' }}"
                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - ($score ?? 0) / 100) }}" />
                    </svg>
                    <span class="text-[1.25rem] font-bold tabular-nums">{{ $score ?? '—' }}<span class="text-[0.75rem]">{{ $score !== null ? '%' : '' }}</span></span>
                </div>
                <div>
                    <p class="text-[1.0625rem] font-semibold">{{ __('Quiz de formation') }}</p>
                    <p class="text-[0.8125rem] opacity-80">{{ $score === null ? __('Non passé') : __('Meilleur score · :passing % requis', ['passing' => $passing]) }}</p>
                </div>
            </x-console.widget>

            <x-console.group :title="__('Identité')">
                <ul class="text-[0.9375rem]">
                    @foreach (array_filter([
                        __('Nom') => $c->last_name,
                        __('Prénoms') => $c->first_names,
                        __('Naissance') => $c->birth_date ? $c->birth_date->translatedFormat('j F Y').' · '.__(':age ans', ['age' => app(AgeRequirement::class)->ageOn($c->birth_date)]) : null,
                        __('Genre') => $c->gender?->label(),
                        __('E-mail') => $c->email,
                    ]) as $label => $value)
                        <li class="flex gap-3 px-4 py-2.5">
                            <span class="w-24 shrink-0 text-mute">{{ $label }}</span>
                            <span class="min-w-0 flex-1 break-words">{{ $value }}</span>
                        </li>
                    @endforeach
                    @if ($canDecide && ($phone = $c->user->phoneNumber()))
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <span class="w-24 shrink-0 text-mute">{{ __('Téléphone') }}</span>
                            <a href="tel:{{ $phone->e164 }}" class="flex-1 font-semibold text-tile-blue hover:opacity-80">{{ $phone->e164 }}</a>
                        </li>
                    @endif
                </ul>
            </x-console.group>

            <x-console.group :title="__('Profil')">
                <ul class="text-[0.9375rem]">
                    @foreach (array_filter([
                        __('Statut') => $c->occupation?->label(),
                        __('Langues') => collect($c->languages ?? [])->map(fn ($code) => $languages[$code] ?? $code)->join(', '),
                        __('Intérêts') => collect($c->interests ?? [])->join(', '),
                        __('Lieu') => $c->lieuType?->name,
                    ]) as $label => $value)
                        <li class="flex gap-3 px-4 py-2.5">
                            <span class="w-24 shrink-0 text-mute">{{ $label }}</span>
                            <span class="min-w-0 flex-1">{{ $value }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-console.group>
        </div>
    </div>

    {{-- Agrandissement d'une pièce --}}
    <div x-show="zoom" x-cloak x-transition.opacity.duration.250ms class="fixed inset-0 z-[70] grid place-items-center bg-black/80 p-6 backdrop-blur-sm" x-on:click="zoom = null" x-on:keydown.escape.window="zoom = null">
        <img :src="zoom" alt="" class="max-h-full max-w-full rounded-[1rem] object-contain shadow-2xl" x-on:click.stop>
    </div>

    {{-- Refus --}}
    <x-console.slide-over model="rejecting" :title="__('Refuser la candidature')" :description="__('Le motif et votre commentaire seront visibles par le candidat.')">
        <form id="reject-form" wire:submit="reject" class="space-y-5">
            <x-console.group :title="__('Motif')">
                <ul>
                    @foreach ($this->reasons as $option)
                        <li>
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem] hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                <input type="radio" wire:model.live="reason" value="{{ $option->uuid }}" class="sr-only">
                                <span class="flex-1">{{ $option->label }}</span>
                                @if ($reason === $option->uuid)<x-ui.icon name="check" class="size-4 text-tile-blue" stroke="3" />@endif
                            </label>
                        </li>
                    @endforeach
                </ul>
            </x-console.group>
            @error('reason') <p class="px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            <div>
                <label for="reject-comment" class="block text-[0.8125rem] font-medium">{{ __('Commentaire') }}</label>
                <textarea id="reject-comment" wire:model="comment" rows="3" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="reject-form" variant="danger" wire:loading.attr="disabled" wire:target="reject">{{ __('Refuser') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    {{-- Complément --}}
    <x-console.slide-over model="complementing" :title="__('Demander un complément')" :description="__('Le candidat retrouvera son dossier modifiable avec votre message.')">
        <form id="complement-form" wire:submit="requestComplement">
            <label for="complement-comment" class="block text-[0.8125rem] font-medium">{{ __('Ce qu’il faut compléter') }}</label>
            <textarea id="complement-comment" wire:model="comment" rows="4" placeholder="{{ __('Ex. : la photo du verso est floue, merci de la reprendre.') }}" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
            @error('comment') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="complement-form" wire:loading.attr="disabled" wire:target="requestComplement">{{ __('Envoyer la demande') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.confirm model="confirmingAccept" action="accept" tone="primary"
        :title="__('Accepter :name ?', ['name' => $name])"
        :text="__('Le candidat devient Relais, reçoit son badge et accède à l’envoi de relais.')"
        :confirm-label="__('Accepter')" />

    <x-console.confirm model="confirmingHold" action="hold" tone="primary"
        :title="__('Mettre en attente ?')"
        :text="__('Le dossier reste dans votre file, marqué en attente.')"
        :confirm-label="__('Mettre en attente')" />
</div>
