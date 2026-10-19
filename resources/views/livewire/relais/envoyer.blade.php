@php
    use App\Domain\Contribution\Enums\CreditMode;
    use App\Domain\Contribution\Enums\PeopleConsent;
    use App\Domain\Contribution\Enums\Urgency;

    $field = 'block h-12 w-full rounded-xl border-0 bg-mist px-4 text-[0.9375rem] text-onyx ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 placeholder:text-graphite/60 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none';
    $label = 'block text-[0.8125rem] font-medium text-onyx';
    $chip = 'press inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full px-4 py-2 text-[0.875rem] font-medium ring-1 transition-[background-color,color,box-shadow] duration-300 select-none';
    $card = 'rounded-[1.75rem] bg-white p-6 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)] sm:p-8';
    $choice = fn (bool $on, string $tone = 'onyx') => [$chip, $tone === 'orange' ? 'bg-relais-500 text-white ring-relais-500' : 'bg-onyx text-white ring-onyx' => $on, 'bg-white text-onyx ring-black/10 hover:ring-black/25' => ! $on];
    $urgencyColors = ['normal' => 'onyx', 'important' => 'orange', 'urgent' => 'orange'];
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    @include('livewire.relais.partials.nav', ['active' => 'send'])

    <header data-enter>
        <h1 class="text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">{{ __('Envoyer un relais') }}</h1>
        <p class="mt-2 text-[1.0625rem] text-graphite">{{ __('Photos, vidéos ou sons : ce que vous avez vu ou entendu vous-même.') }}</p>
    </header>

    @if ($this->appelModel)
        <div class="flex items-start gap-3 rounded-2xl bg-tile-purple/10 p-4" data-enter>
            <x-ui.icon name="bell" class="mt-0.5 size-5 shrink-0 text-tile-purple" />
            <p class="text-[0.9375rem]"><span class="font-semibold">{{ __('Réponse à l’appel « :title »', ['title' => $this->appelModel->title]) }}</span><br><span class="text-graphite">{{ $this->appelModel->instructions }}</span></p>
        </div>
        @error('appel') <p class="text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
    @endif

    {{-- Médias (WEB-04) --}}
    <section class="{{ $card }}" data-enter
        x-data="webUploader({
            announceUrl: @js(route('relais.medias.store', $relaisUuid)),
            statusUrl: @js(route('relais.medias.show', '__MEDIA__')),
            completeUrl: @js(route('relais.medias.complete', '__MEDIA__')),
        })">
        <h2 class="text-[1.25rem] font-semibold tracking-[-0.02em]">{{ __('Vos médias') }}</h2>
        <p class="mt-1 text-[0.875rem] text-graphite">{{ __('Jusqu’à :count fichiers. Les gros fichiers partent en morceaux : si la connexion coupe, l’envoi reprend là où il s’était arrêté.', ['count' => app(\App\Domain\Settings\Settings::class)->integer('media.max_medias_per_relais')]) }}</p>

        <label class="mt-5 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-black/15 bg-mist/60 px-6 py-10 text-center transition-colors hover:border-relais-500/50 hover:bg-relais-500/[0.04]">
            <span class="grid size-14 place-items-center rounded-2xl bg-relais-500 text-white shadow-[0_10px_24px_-10px_rgb(242_140_40/0.8)]"><x-ui.icon name="camera" class="size-7" /></span>
            <span class="text-[1rem] font-semibold">{{ __('Ajouter des photos, vidéos ou sons') }}</span>
            <span class="text-[0.8125rem] text-graphite">JPEG, PNG, HEIC · MP4, MOV · M4A, MP3, AAC</span>
            <input type="file" multiple class="sr-only" accept="image/jpeg,image/png,image/heic,.heic,video/mp4,video/quicktime,audio/mp4,audio/x-m4a,audio/mpeg,audio/aac" x-on:change="add($event)">
        </label>

        <ul class="mt-4 space-y-2" x-show="files.length" x-cloak>
            <template x-for="file in files" :key="file.id">
                <li class="flex items-center gap-3 rounded-2xl bg-mist px-4 py-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-relais-500">
                        <svg x-show="file.kind === 'photo'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m21 16-5-5-8 8"/></svg>
                        <svg x-show="file.kind === 'video'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/></svg>
                        <svg x-show="file.kind === 'audio'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[0.9375rem] font-medium" x-text="file.name"></p>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-black/[0.07]">
                            <div class="h-full rounded-full transition-[width] duration-300" :class="file.status === 'failed' || file.status === 'rejected' ? 'bg-status-rejected' : (file.progress === 100 ? 'bg-tile-green' : 'bg-relais-500')" :style="`width: ${file.progress}%`"></div>
                        </div>
                        <p class="mt-1 text-[0.75rem] text-graphite">
                            <span x-text="sizeLabel(file.size)"></span> ·
                            <span x-text="{ waiting: 'En attente', uploading: `Envoi ${file.progress} %`, uploaded: 'Reçu, contrôle en cours', processing: 'Contrôle en cours', ready: 'Reçu et contrôlé', rejected: 'Refusé', failed: 'Interrompu' }[file.status]"></span>
                            <span class="text-status-rejected" x-show="file.error" x-text="' · ' + file.error"></span>
                        </p>
                    </div>
                    <button type="button" x-show="file.status === 'failed'" x-on:click="run()" class="press rounded-full bg-onyx px-3 py-1.5 text-[0.8125rem] font-semibold text-white">{{ __('Réessayer') }}</button>
                </li>
            </template>
        </ul>
        @if (isset($missing['medias']))<p class="mt-3 text-[0.8125rem] font-medium text-status-rejected">{{ $missing['medias'] }}</p>@endif
    </section>

    <form wire:submit="send" class="space-y-6">
        {{-- Le relais (MOB-14) --}}
        <section class="{{ $card }} space-y-6" data-enter>
            <h2 class="text-[1.25rem] font-semibold tracking-[-0.02em]">{{ __('Ce qui se passe') }}</h2>
            <fieldset>
                <legend class="{{ $label }}">{{ __('Catégorie') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($this->categories as $item)
                        <label @class($choice($category === $item->uuid))>
                            <input type="radio" wire:model.live="category" value="{{ $item->uuid }}" class="sr-only">{{ $item->name }}
                        </label>
                    @endforeach
                </div>
                @error('category') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </fieldset>
            <div>
                <label for="title" class="{{ $label }}">{{ __('Titre court') }}</label>
                <input id="title" wire:model="title" maxlength="120" class="{{ $field }} mt-1.5" placeholder="{{ __('Ex. : Route coupée par les eaux à Niangon') }}">
                @error('title') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="description" class="{{ $label }}">{{ __('Description') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                <textarea id="description" wire:model="description" rows="3" maxlength="2000" class="{{ $field }} mt-1.5 h-auto py-3" placeholder="{{ __('Où, quand, ce que vous voyez.') }}"></textarea>
            </div>
            <fieldset>
                <legend class="{{ $label }}">{{ __('Urgence') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (Urgency::cases() as $item)
                        <label @class($choice($urgency === $item->value, $urgencyColors[$item->value]))>
                            <input type="radio" wire:model.live="urgency" value="{{ $item->value }}" class="sr-only">{{ $item->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        {{-- Lieu (MOB-13, MOB-14) --}}
        <section class="{{ $card }} space-y-5" data-enter x-data="{ locating: false, located: false, locate() { if (! navigator.geolocation) return; this.locating = true; navigator.geolocation.getCurrentPosition((position) => { $wire.set('lat', String(position.coords.latitude)); $wire.set('lng', String(position.coords.longitude)); $wire.set('accuracy', String(position.coords.accuracy)); this.locating = false; this.located = true }, () => { this.locating = false }, { enableHighAccuracy: true, timeout: 15000 }) } }">
            <h2 class="text-[1.25rem] font-semibold tracking-[-0.02em]">{{ __('Où ?') }}</h2>
            <button type="button" x-on:click="locate()" class="press inline-flex items-center gap-2 rounded-full bg-mist px-4 py-2.5 text-[0.9375rem] font-medium ring-1 ring-black/10 hover:ring-black/25">
                <x-ui.icon name="map-pin" class="size-4 text-relais-500" />
                <span x-show="! located && ! locating">{{ __('Utiliser ma position actuelle') }}</span>
                <span x-show="locating" x-cloak>{{ __('Localisation…') }}</span>
                <span x-show="located" x-cloak>{{ __('Position enregistrée') }}</span>
            </button>
            <p class="text-[0.8125rem] text-graphite">{{ __('Uniquement si vous êtes encore sur place. Sinon, indiquez la commune et le lieu.') }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="commune" class="{{ $label }}">{{ __('Commune') }}</label>
                    <select id="commune" wire:model="declaredCommune" class="{{ $field }} mt-1.5">
                        @foreach ($this->communes as $item)<option value="{{ $item->uuid }}">{{ $item->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="place" class="{{ $label }}">{{ __('Lieu précis') }} <span class="font-normal text-graphite">{{ __('(facultatif)') }}</span></label>
                    <input id="place" wire:model="declaredPlace" maxlength="200" class="{{ $field }} mt-1.5" placeholder="{{ __('Carrefour, marché, rue…') }}">
                </div>
            </div>
            @if (isset($missing['declared_commune']))<p class="text-[0.8125rem] font-medium text-status-rejected">{{ $missing['declared_commune'] }}</p>@endif
        </section>

        {{-- Protection des personnes (MOB-15) --}}
        <section class="{{ $card }} space-y-6" data-enter>
            <h2 class="text-[1.25rem] font-semibold tracking-[-0.02em]">{{ __('Protection des personnes') }}</h2>
            <fieldset>
                <legend class="{{ $label }}">{{ __('Des mineurs sont-ils reconnaissables ?') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (['1' => __('Oui'), '0' => __('Non')] as $value => $text)
                        <label @class($choice($hasMinors === (string) $value))><input type="radio" wire:model.live="hasMinors" value="{{ $value }}" class="sr-only">{{ $text }}</label>
                    @endforeach
                </div>
                @error('hasMinors') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </fieldset>
            <fieldset>
                <legend class="{{ $label }}">{{ __('Les personnes filmées sont-elles d’accord ?') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (PeopleConsent::cases() as $item)
                        <label @class($choice($peopleConsent === $item->value))><input type="radio" wire:model.live="peopleConsent" value="{{ $item->value }}" class="sr-only">{{ $item->label() }}</label>
                    @endforeach
                </div>
                @error('peopleConsent') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </fieldset>
            <fieldset>
                <legend class="{{ $label }}">{{ __('À l’antenne, vous souhaitez…') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (CreditMode::cases() as $item)
                        <label @class($choice($creditMode === $item->value))><input type="radio" wire:model.live="creditMode" value="{{ $item->value }}" class="sr-only">{{ $item->label() }}</label>
                    @endforeach
                </div>
                @if ($creditMode === 'pseudonym')
                    <input wire:model="creditName" maxlength="80" class="{{ $field }} mt-3" placeholder="{{ __('Votre pseudonyme') }}">
                @endif
                @error('creditMode') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                @error('creditName') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </fieldset>
        </section>

        @if ($missing !== [])
            <div class="rounded-2xl bg-status-rejected/[0.07] p-5" role="alert">
                <p class="text-[0.9375rem] font-semibold text-status-rejected">{{ __('Il manque des informations.') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-[0.875rem]">@foreach ($missing as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex items-center justify-between gap-4" data-enter>
            <p class="text-[0.8125rem] text-graphite">{{ __('En envoyant, vous confirmez être l’auteur de ces médias et respecter la charte.') }}</p>
            <x-ui.button type="submit" size="lg" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="send">{{ __('Envoyer le relais') }}</x-ui.button>
        </div>
    </form>
</div>
