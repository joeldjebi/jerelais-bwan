<x-layouts.public :title="__('Vérification d’un badge')">
    <section class="flex min-h-[80svh] items-center justify-center bg-mist px-5 pt-28 pb-20">
        <div class="w-full max-w-md text-center" data-anim>
            @if ($profile)
                @php $active = $profile->status->canSubmit(); @endphp
                <span @class(['mx-auto grid size-20 place-items-center rounded-full text-white', 'bg-tile-green' => $active, 'bg-status-rejected' => ! $active])>
                    <x-ui.icon :name="$active ? 'check' : 'x'" class="size-10" stroke="2.5" />
                </span>
                <h1 class="mt-6 text-[clamp(2rem,6vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">
                    {{ $active ? __('Relais vérifié.') : __('Badge inactif.') }}
                </h1>
                <div class="mt-8 rounded-[1.75rem] bg-white p-6 text-left shadow-[0_24px_48px_-28px_rgb(0_0_0/0.18)]">
                    <dl class="divide-y divide-black/[0.06] text-[0.9375rem]">
                        <div class="flex justify-between gap-4 py-3"><dt class="text-graphite">{{ __('Relais') }}</dt><dd class="font-semibold">{{ $profile->display_name }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-graphite">{{ __('Commune') }}</dt><dd class="font-semibold">{{ $profile->commune?->name }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-graphite">{{ __('Numéro') }}</dt><dd class="font-mono font-semibold">{{ $profile->number }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-graphite">{{ __('Statut') }}</dt><dd class="font-semibold">{{ $profile->status->label() }}</dd></div>
                    </dl>
                </div>
                <p class="mt-6 text-[0.875rem] leading-relaxed text-graphite">{{ __('Un Relais est un observateur citoyen de REFLET TV. Il n’est ni journaliste ni salarié de la chaîne, et ce badge n’est pas une carte de presse.') }}</p>
            @else
                <span class="mx-auto grid size-20 place-items-center rounded-full bg-black/[0.06] text-graphite"><x-ui.icon name="x" class="size-10" /></span>
                <h1 class="mt-6 text-[2.25rem] font-semibold tracking-[-0.035em]">{{ __('Badge introuvable.') }}</h1>
                <p class="mt-3 text-graphite">{{ __('Ce code ne correspond à aucun Relais de REFLET TV.') }}</p>
            @endif
        </div>
    </section>
</x-layouts.public>
