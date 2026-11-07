{{-- Palette de commandes (⌘K / Ctrl+K) : accès clavier à toute la console. --}}
@props(['commands'])

<div
    x-data="commandPalette(@js($commands))"
    x-on:palette-open.window="show()"
    x-on:keydown.window.meta.k.prevent="open ? hide() : show()"
    x-on:keydown.window.ctrl.k.prevent="open ? hide() : show()"
    x-on:keydown.escape.window="hide()"
    x-cloak
>
    <div x-show="open" x-transition.opacity.duration.250ms class="fixed inset-0 z-[60] bg-black/25 backdrop-blur-[2px]" x-on:click="hide()"></div>

    <div
        x-show="open"
        x-transition:enter="transition duration-300 ease-out-expo"
        x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-[0.98]"
        class="fixed inset-x-4 top-[14vh] z-[61] mx-auto max-w-xl overflow-hidden rounded-[1.4rem] bg-white/85 shadow-[0_0_0_0.5px_rgb(0_0_0/0.1),0_40px_100px_-20px_rgb(0_0_0/0.45)] backdrop-blur-2xl backdrop-saturate-150 dark:bg-[#2c2c2e]/85"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Palette de commandes') }}"
    >
        <div class="flex items-center gap-3 border-b border-line px-5">
            <x-ui.icon name="search" class="size-[1.15rem] text-mute" />
            <input
                x-ref="input"
                x-model="query"
                x-on:input="cursor = 0"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="run()"
                type="text"
                placeholder="{{ __('Rechercher une page, une action…') }}"
                class="h-14 w-full border-0 bg-transparent text-[0.9375rem] placeholder:text-mute focus:ring-0 focus:outline-none"
                aria-label="{{ __('Rechercher') }}"
            >
            <kbd class="rounded-md px-1.5 py-0.5 font-sans text-[0.6875rem] text-mute ring-1 ring-line">esc</kbd>
        </div>

        <ul x-ref="list" class="max-h-[50vh] overflow-y-auto p-2" role="listbox">
            <template x-for="(command, index) in results" :key="command.url">
                <li>
                    <button
                        type="button"
                        x-on:click="run(command)"
                        x-on:mouseenter="cursor = index"
                        :data-active="cursor === index ? true : null"
                        :class="cursor === index ? 'bg-tile-blue text-white [&_.text-mute]:text-white/75' : 'text-ink'"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[0.875rem] transition-colors duration-150"
                        role="option"
                    >
                        <span class="grid size-7 place-items-center rounded-[0.5rem] text-white" :class="{ orange: 'bg-tile-orange', blue: 'bg-tile-blue', green: 'bg-tile-green', gray: 'bg-tile-gray', purple: 'bg-tile-purple', teal: 'bg-tile-teal', pink: 'bg-tile-pink' }[command.tint] ?? 'bg-tile-gray'">
                            <x-ui.icon name="arrow-right" class="size-3.5" stroke="2.5" />
                        </span>
                        <span class="flex-1 font-medium" x-text="command.label"></span>
                        <span class="text-xs text-mute" x-text="command.group"></span>
                    </button>
                </li>
            </template>
            <li x-show="results.length === 0" class="px-3 py-10 text-center text-sm text-mute">{{ __('Aucun résultat.') }}</li>
        </ul>

        <footer class="flex items-center gap-4 border-t border-line px-5 py-2.5 text-[0.6875rem] text-mute">
            <span><kbd class="font-sans">↑↓</kbd> {{ __('naviguer') }}</span>
            <span><kbd class="font-sans">↵</kbd> {{ __('ouvrir') }}</span>
            <span class="ml-auto">JE RELAIS</span>
        </footer>
    </div>
</div>
