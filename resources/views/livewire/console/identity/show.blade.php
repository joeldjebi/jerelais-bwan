@php
    use App\Domain\Identity\Enums\IdentityFileKind;
    use App\Domain\Identity\Enums\IdentityStatus;
    use Illuminate\Support\Facades\URL;

    $v = $this->verification;
    $minutes = (int) config('jerelais.candidature.identity_url_minutes');
    $files = $v->files->keyBy(fn ($file) => $file->kind->value);
    $url = fn ($file) => $file ? URL::temporarySignedRoute('console.identity.file', now()->addMinutes($minutes), $file) : null;
    $identity = $this->identity;
    $phone = $v->user?->phoneNumber();
    $name = $v->user?->name ?: __('Sans nom');
    $tone = ['draft' => 'neutral', 'submitted' => 'info', 'verified' => 'success', 'rejected' => 'warning'];
    $kinds = [...($v->documentType?->sides() ?? []), IdentityFileKind::Selfie, IdentityFileKind::ProfilePhoto];
@endphp

<div class="space-y-7" x-data="{ zoom: null }">
    <a href="{{ route('console.identity') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Vérifications d’identité') }}
    </a>

    <x-console.page-header :eyebrow="__('Vérification d’identité')" :title="$name" :subtitle="$v->documentType?->name ?? __('Type de pièce non choisi')">
        <x-slot:actions>
            <x-console.badge :tone="$tone[$v->status->value] ?? 'neutral'">{{ $v->status->label() }}</x-console.badge>
        </x-slot:actions>
    </x-console.page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_21rem]">
        <x-console.group :title="__('Pièces')" :footer="__('Chaque ouverture d’un fichier est enregistrée dans le journal d’audit. Cliquez pour agrandir.')">
            <div class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-3">
                @foreach ($kinds as $kind)
                    @php $file = $files[$kind->value] ?? null; $src = $url($file); @endphp
                    <figure class="min-w-0">
                        @if ($src && $file->isImage())
                            <button type="button" x-on:click="zoom = @js($src)" class="block aspect-[3/4] w-full overflow-hidden rounded-[0.9rem] bg-black/[0.04] transition-transform duration-500 ease-spring hover:scale-[1.02]">
                                <img src="{{ $src }}" alt="{{ $kind->label() }}" class="size-full object-cover" loading="lazy">
                            </button>
                        @elseif ($src)
                            <a href="{{ $src }}" target="_blank" rel="noopener" class="grid aspect-[3/4] w-full place-items-center rounded-[0.9rem] bg-black/[0.04] text-[0.8125rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Ouvrir le PDF') }}</a>
                        @else
                            <div class="grid aspect-[3/4] w-full place-items-center rounded-[0.9rem] bg-black/[0.04] text-[0.75rem] text-mute">{{ __('Non fourni') }}</div>
                        @endif
                        <figcaption class="mt-1.5 truncate text-center text-[0.75rem] font-medium text-mute">{{ $kind->label() }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </x-console.group>

        <div class="space-y-6">
            <x-console.group :title="__('Identité déclarée')">
                <dl class="divide-y divide-line text-[0.9375rem]">
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Nom') }}</dt><dd class="text-right font-medium">{{ $identity?->last_name ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Prénoms') }}</dt><dd class="text-right font-medium">{{ $identity?->first_names ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Sexe') }}</dt><dd class="text-right font-medium">{{ $identity?->gender?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Naissance') }}</dt><dd class="text-right font-medium">{{ $identity?->birth_date ? $identity->birth_date->translatedFormat('j F Y').' ('.__(':age ans', ['age' => (int) $identity->birth_date->diffInYears(now())]).')' : '—' }}</dd></div>
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Commune') }}</dt><dd class="text-right font-medium">{{ collect([$v->commune?->name, $v->localite?->name])->filter()->join(' · ') ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ __('Téléphone') }}</dt><dd class="text-right font-medium">@if ($phone)<a href="tel:{{ $phone->e164 }}" class="text-tile-blue hover:opacity-80">{{ $phone->e164 }}</a>@else — @endif</dd></div>
                </dl>
            </x-console.group>

            @if ($v->status === IdentityStatus::Rejected && $v->decision_comment)
                <x-console.group :title="__('Correction demandée')">
                    <p class="px-4 py-3 text-[0.9375rem] whitespace-pre-line">{{ $v->decision_comment }}</p>
                </x-console.group>
            @endif

            @if ($this->canDecide())
                <div class="flex flex-col gap-2">
                    <x-ui.button wire:click="$set('confirmingVerify', true)">{{ __('Identité vérifiée') }}</x-ui.button>
                    <x-ui.button variant="secondary" class="text-status-rejected!" wire:click="$set('rejecting', true)">{{ __('Demander une correction') }}</x-ui.button>
                </div>
            @endif
        </div>
    </div>

    <div x-show="zoom" x-cloak x-transition.opacity.duration.250ms class="fixed inset-0 z-[70] grid place-items-center bg-black/80 p-6 backdrop-blur-sm" x-on:click="zoom = null" x-on:keydown.escape.window="zoom = null">
        <img :src="zoom" alt="" class="max-h-full max-w-full rounded-[1rem] object-contain shadow-2xl" x-on:click.stop>
    </div>

    <x-console.slide-over model="rejecting" :title="__('Demander une correction')" :description="__('Votre message sera affiché au Relais dans l’application.')">
        <form id="identity-reject-form" wire:submit="reject">
            <label for="identity-comment" class="block text-[0.8125rem] font-medium">{{ __('Ce qu’il faut corriger') }}</label>
            <textarea id="identity-comment" wire:model="comment" rows="4" placeholder="{{ __('Ex. : le verso de la CNI est flou, merci de le reprendre.') }}" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]"></textarea>
            @error('comment') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="identity-reject-form" variant="danger" wire:loading.attr="disabled" wire:target="reject">{{ __('Envoyer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.confirm model="confirmingVerify" action="verify" tone="primary"
        :title="__('Valider l’identité de :name ?', ['name' => $name])"
        :text="__('Le Relais devient éligible aux gains et est rattaché à sa commune déclarée.')"
        :confirm-label="__('Valider')" />
</div>
