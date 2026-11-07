@php
    use App\Livewire\Console\Settings\Index as SettingsPage;

    $groupTint = ['media' => 'orange', 'validation' => 'blue', 'appels' => 'purple', 'alerts' => 'pink', 'levels' => 'orange', 'candidature' => 'green', 'retention' => 'gray'];
    $icons = [
        'media.video_max_seconds' => 'video', 'media.audio_max_seconds' => 'mic', 'media.max_medias_per_relais' => 'photo', 'media.max_file_size_mb' => 'layers',
        'validation.sla_urgent_minutes' => 'bolt', 'validation.sla_normal_minutes' => 'clock', 'validation.lock_minutes' => 'shield',
        'appels.local_requires_approval' => 'bell',
        'levels.silver_validated' => 'signal', 'levels.gold_validated' => 'signal',
        'alerts.surge_threshold' => 'chart', 'alerts.surge_window_minutes' => 'clock',
        'candidature.quiz_min_score' => 'book', 'candidature.minimum_age' => 'users', 'candidature.minors_allowed' => 'users',
    ];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Paramètres')"
        :subtitle="__('Règles de fonctionnement de la plateforme. Chaque modification est enregistrée dans le journal d’audit.')"
    />

    <div class="mx-auto max-w-3xl space-y-8">
        @foreach ($this->groups() as $group => $keys)
            @php [$groupTitle, $groupDescription] = __("settings.groups.{$group}"); @endphp
            <form id="groupe-{{ $group }}" wire:submit="save('{{ $group }}')" class="scroll-mt-24">
                <x-console.group :title="$groupTitle" :footer="$groupDescription">
                    <x-slot:actions>
                        <span wire:loading wire:target="save('{{ $group }}')" class="text-[0.75rem] text-mute">{{ __('Enregistrement…') }}</span>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save('{{ $group }}')" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80 disabled:opacity-40">{{ __('Enregistrer') }}</button>
                    </x-slot:actions>

                    <ul>
                        @foreach ($keys as $key)
                            @php
                                $field = SettingsPage::field($key);
                                $default = config('jerelais.settings')[$key];
                            @endphp
                            <li class="flex items-center gap-3.5 px-4 py-2.5">
                                <x-console.tile :icon="$icons[$key] ?? 'inbox'" :tint="$groupTint[$group]" />
                                @if (is_bool($default))
                                    <div class="flex-1">
                                        <x-console.toggle :label="__('settings.labels.'.$key)" name="values.{{ $field }}" wire:model="values.{{ $field }}"
                                            :description="$key === 'candidature.minors_allowed' ? __('Une autorisation parentale signée sera alors exigée (§9.3).') : null" />
                                    </div>
                                @else
                                    <div class="min-w-0 flex-1">
                                        <label for="values.{{ $field }}" class="block text-[0.9375rem]">{{ __('settings.labels.'.$key) }}</label>
                                        <p class="text-[0.75rem] text-mute">
                                            {{ $default === null ? __('Par défaut : aucune purge') : __('Par défaut : :value :unit', ['value' => $default, 'unit' => __('settings.units.'.$key)]) }}
                                        </p>
                                        @error('values.'.$field) <p class="mt-0.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input id="values.{{ $field }}" type="number" min="1" inputmode="numeric" wire:model="values.{{ $field }}"
                                            placeholder="{{ $default === null ? __('Illimité') : '' }}"
                                            class="h-9 w-24 rounded-[0.6rem] border-0 bg-black/[0.055] px-3 text-right text-[0.9375rem] font-medium tabular-nums placeholder:text-mute focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]">
                                        <span class="w-12 text-[0.8125rem] text-mute">{{ __('settings.units.'.$key) }}</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-console.group>
            </form>
        @endforeach
    </div>
</div>
