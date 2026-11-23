{{--
    Choix du visuel public d'un relais sur la vitrine (WEB-05, ADM-17).
    Variables : $relais (RelaisItem), $current (valeur choisie), $hasThumbnail (bool), $upload (bool : dépôt possible).
--}}
@php
    use App\Domain\Editorial\Enums\ShowcaseVisual;
    use App\Domain\Editorial\Support\ShowcaseVisuals;

    $mustBlur = $relais->blur_required || $relais->has_minors;
    $options = [
        ShowcaseVisual::None->value => [true, __('Titre et texte seulement.')],
        ShowcaseVisual::Original->value => [! $mustBlur && $hasThumbnail, $mustBlur ? __('Interdit : floutage demandé ou mineurs.') : ($hasThumbnail ? __('Vignette de la photo ou de la vidéo envoyée.') : __('Aucune image contrôlée.'))],
        ShowcaseVisual::Blurred->value => [$hasThumbnail, $hasThumbnail ? __('Le serveur floute toute l’image : aucun visage reconnaissable.') : __('Aucune image contrôlée à flouter.')],
        ShowcaseVisual::Edited->value => [$upload, $relais->showcase_media_path ? __('Version déposée : la remplacer ou la garder.') : __('Photo ou vidéo floutée par le monteur (100 Mo au plus).')],
    ];
@endphp

<fieldset class="space-y-2">
    <legend class="text-[0.8125rem] font-medium">{{ __('Visuel public') }}</legend>
    @if ($mustBlur)
        <p class="rounded-[0.8rem] bg-status-pending/12 px-3 py-2 text-[0.8125rem]">{{ __('Floutage demandé ou mineurs : l’image d’origine n’est jamais publiée.') }}</p>
    @endif
    @foreach ($options as $value => [$enabled, $hint])
        <label @class([
            'flex items-start gap-3 rounded-[0.9rem] px-3.5 py-2.5 ring-1 transition',
            'cursor-pointer ring-line hover:bg-black/[0.02]' => $enabled && $current !== $value,
            'cursor-pointer bg-tile-blue/[0.06] ring-tile-blue/40' => $enabled && $current === $value,
            'cursor-not-allowed opacity-45 ring-line' => ! $enabled,
        ])>
            <input type="radio" wire:model.live="showcaseVisual" value="{{ $value }}" @disabled(! $enabled) class="mt-1">
            <span>
                <span class="block text-[0.9375rem] font-medium">{{ ShowcaseVisual::from($value)->label() }}</span>
                <span class="block text-[0.8125rem] text-mute">{{ $hint }}</span>
            </span>
        </label>
    @endforeach

    @if ($upload && $current === ShowcaseVisual::Edited->value)
        <div class="rounded-[0.9rem] bg-black/[0.035] p-3.5 dark:bg-white/[0.05]" x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress">
            <label for="edited-file" class="block text-[0.8125rem] font-medium">{{ __('Version floutée (JPEG, PNG, MP4 ou MOV, :max Mo au plus)', ['max' => ShowcaseVisuals::EDITED_MAX_MB]) }}</label>
            <input id="edited-file" type="file" wire:model="editedFile" accept="image/jpeg,image/png,video/mp4,video/quicktime" class="mt-2 block w-full text-[0.875rem]">
            <div x-show="uploading" x-cloak class="mt-2 h-1.5 overflow-hidden rounded-full bg-black/10"><div class="h-full bg-tile-blue transition-all" :style="`width: ${progress}%`"></div></div>
            @error('editedFile') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </div>
    @endif
</fieldset>
