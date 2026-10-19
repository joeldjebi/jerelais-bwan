@php
    use App\Livewire\Console\Settings\Index as SettingsPage;
@endphp

<div class="space-y-8">
    <x-console.page-header
        index="03"
        :eyebrow="__('Administration')"
        :title="__('Paramètres')"
        :subtitle="__('Règles de fonctionnement de la plateforme. Chaque modification est enregistrée dans le journal d’audit.')"
    />

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <nav class="hidden lg:block" aria-label="{{ __('Sections des paramètres') }}">
            <ul class="sticky top-24 space-y-0.5 text-[0.875rem]">
                @foreach ($this->groups() as $group => $keys)
                    <li>
                        <a href="#groupe-{{ $group }}" class="block rounded-lg px-3 py-2 text-ink-soft transition-colors duration-300 hover:bg-ink/[0.04] hover:text-ink">{{ __("settings.groups.{$group}")[0] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="space-y-6">
            @foreach ($this->groups() as $group => $keys)
                @php [$groupTitle, $groupDescription] = __("settings.groups.{$group}"); @endphp
                <form id="groupe-{{ $group }}" wire:submit="save('{{ $group }}')" class="scroll-mt-24">
                    <x-console.panel :title="$groupTitle" :description="$groupDescription" :padded="false">
                        <div class="divide-y divide-line">
                            @foreach ($keys as $key)
                                @php
                                    $field = SettingsPage::field($key);
                                    $default = config('jerelais.settings')[$key];
                                @endphp
                                <div class="grid items-center gap-3 px-6 py-4 sm:grid-cols-[1fr_14rem]">
                                    @if (is_bool($default))
                                        <div class="sm:col-span-2">
                                            <x-console.toggle :label="__('settings.labels.'.$key)" name="values.{{ $field }}" wire:model="values.{{ $field }}"
                                                :description="__('Si activé, une autorisation parentale signée sera exigée (§9.3).')" />
                                        </div>
                                    @else
                                        <div>
                                            <label for="values.{{ $field }}" class="text-[0.875rem] font-medium">{{ __('settings.labels.'.$key) }}</label>
                                            <p class="text-xs text-mute">
                                                {{ $default === null ? __('Par défaut : aucune purge') : __('Par défaut : :value :unit', ['value' => $default, 'unit' => __('settings.units.'.$key)]) }}
                                            </p>
                                        </div>
                                        <x-console.input name="values.{{ $field }}" :label="''" type="number" min="1" inputmode="numeric"
                                            wire:model="values.{{ $field }}" :suffix="__('settings.units.'.$key)" :placeholder="$default === null ? __('Illimité') : null" class="text-right tabular-nums" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-line bg-ink/[0.015] px-6 py-3">
                            <span wire:loading wire:target="save('{{ $group }}')" class="text-xs text-mute">{{ __('Enregistrement…') }}</span>
                            <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="save('{{ $group }}')">{{ __('Enregistrer') }}</x-ui.button>
                        </div>
                    </x-console.panel>
                </form>
            @endforeach
        </div>
    </div>
</div>
