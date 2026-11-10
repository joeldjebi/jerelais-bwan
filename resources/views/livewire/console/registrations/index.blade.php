@php
    use Illuminate\Support\Facades\URL;

    $minutes = (int) config('jerelais.candidature.identity_url_minutes');
@endphp

<div class="space-y-8" x-data="{ zoom: null }">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Inscriptions')"
        :subtitle="__('Comptes créés depuis l’application, en attente de validation. Vérifiez la pièce d’identité envoyée : chaque ouverture est tracée. Tant qu’une inscription n’est pas validée, la personne ne peut pas se connecter.')"
    />

    <x-console.group :title="trans_choice(':count inscription en attente|:count inscriptions en attente', $this->registrations->total())">
        <x-slot:actions>
            <span wire:loading wire:target="search,nextPage,previousPage" class="size-4 animate-spin rounded-full border-2 border-line border-t-tile-blue"></span>
        </x-slot:actions>

        <div class="border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Nom ou prénoms')" />
        </div>

        @if ($this->registrations->isEmpty())
            <x-console.empty icon="users" tint="pink" :title="__('Aucune inscription en attente')" :text="__('Les nouvelles inscriptions faites depuis l’application apparaîtront ici.')" />
        @else
            <ul wire:loading.class="opacity-50" wire:target="search" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->registrations as $registration)
                    @php
                        $candidature = $registration->candidature;
                        $initials = Str::of($registration->name ?? '?')->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
                        $phone = $registration->phoneNumber();
                        $details = collect([
                            $candidature?->gender?->label(),
                            $candidature?->birth_date ? __(':date (:age ans)', ['date' => $candidature->birth_date->translatedFormat('j F Y'), 'age' => (int) $candidature->birth_date->diffInYears(now())]) : null,
                        ])->filter()->join(' · ');
                    @endphp
                    <li wire:key="registration-{{ $registration->uuid }}" class="flex flex-wrap items-center gap-3.5 px-4 py-3 sm:flex-nowrap">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-tile-pink text-[0.8125rem] font-semibold text-white">{{ $initials }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate text-[0.9375rem] font-semibold">
                                {{ $registration->name }}
                                @if ($candidature?->is_minor)<x-console.badge tone="warning">{{ __('Mineur·e') }}</x-console.badge>@endif
                            </p>
                            <p class="truncate text-[0.8125rem] text-mute">
                                {{ $details }}
                                @if ($phone) · <a href="tel:{{ $phone->e164 }}" class="font-medium text-tile-blue hover:opacity-80">{{ $phone->e164 }}</a>@endif
                            </p>
                            @if ($verification = $registration->identityVerification)
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.8125rem]">
                                    <span class="text-mute">{{ $verification->document_type?->label() }}</span>
                                    @foreach ($verification->files as $file)
                                        @php $src = URL::temporarySignedRoute('console.identity.file', now()->addMinutes($minutes), $file); @endphp
                                        @if ($file->isImage())
                                            <button type="button" x-on:click="zoom = @js($src)" class="font-semibold text-tile-blue hover:opacity-80">{{ $file->kind->label() }}</button>
                                        @else
                                            <a href="{{ $src }}" target="_blank" rel="noopener" class="font-semibold text-tile-blue hover:opacity-80">{{ $file->kind->label() }} (PDF)</a>
                                        @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <span class="hidden w-24 text-right text-[0.8125rem] text-mute md:block">{{ $registration->created_at?->diffForHumans(short: true) }}</span>
                        <div class="flex shrink-0 gap-1.5">
                            <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="ask('{{ $registration->uuid }}', 'reject')">{{ __('Refuser') }}</x-ui.button>
                            <x-ui.button size="sm" wire:click="ask('{{ $registration->uuid }}', 'approve')">{{ __('Valider') }}</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($this->registrations->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->registrations->firstItem(), 'to' => $this->registrations->lastItem(), 'total' => $this->registrations->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->registrations->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->registrations->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>

    <div x-show="zoom" x-cloak x-transition.opacity.duration.250ms class="fixed inset-0 z-[70] grid place-items-center bg-black/80 p-6 backdrop-blur-sm" x-on:click="zoom = null" x-on:keydown.escape.window="zoom = null">
        <img :src="zoom" alt="" class="max-h-full max-w-full rounded-[1rem] object-contain shadow-2xl" x-on:click.stop>
    </div>

    <x-console.confirm
        model="confirming"
        action="confirm"
        :tone="$decision === 'approve' ? 'primary' : 'danger'"
        :title="$decision === 'approve' ? __('Valider l’inscription de :name ?', ['name' => $this->selected?->name]) : __('Refuser l’inscription de :name ?', ['name' => $this->selected?->name])"
        :text="$decision === 'approve'
            ? __('La personne pourra se connecter à l’application avec son numéro et son code PIN, puis compléter sa candidature.')
            : __('Le compte et ses données sont effacés. Le numéro pourra servir à une nouvelle inscription. L’action est tracée.')"
        :confirm-label="$decision === 'approve' ? __('Valider') : __('Refuser')"
    />
</div>
