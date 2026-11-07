{{-- Badge numérique du Relais (MOB-07) : photo, numéro, commune, QR code de vérification. --}}
@props(['profile'])

@php
    use App\Domain\Relais\Support\BadgeQrCode;
    use Illuminate\Support\Facades\URL;

    $photo = $profile->candidature->documents->firstWhere('type', \App\Domain\Candidature\Enums\DocumentType::ProfilePhoto);
    $photoUrl = $photo ? URL::temporarySignedRoute('relais.badge.photo', now()->addMinutes(15), $profile) : null;
@endphp

<article {{ $attributes->merge(['class' => 'relative mx-auto w-full max-w-[22rem] overflow-hidden rounded-[2rem] bg-onyx text-white shadow-[0_40px_80px_-30px_rgb(0_0_0/0.55)]']) }} data-badge>
    <div class="flex items-center justify-between px-6 pt-6">
        <span class="flex items-center gap-2 text-[0.8125rem] font-semibold tracking-wide">
            <span class="grid size-7 place-items-center rounded-lg bg-relais-500"><x-ui.icon name="signal" class="size-4" /></span>
            JE RELAIS
        </span>
        <span class="text-[0.6875rem] font-medium tracking-[0.18em] text-white/60 uppercase">REFLET TV</span>
    </div>

    <div class="flex items-center gap-4 px-6 pt-7">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="" class="size-20 rounded-2xl object-cover ring-2 ring-white/15">
        @else
            <span class="grid size-20 place-items-center rounded-2xl bg-white/10 text-2xl font-semibold">{{ Str::upper(Str::substr($profile->display_name, 0, 1)) }}</span>
        @endif
        <div class="min-w-0">
            <p class="text-[0.75rem] font-medium tracking-[0.14em] text-relais-400 uppercase">{{ __('Relais') }}</p>
            <p class="truncate text-[1.5rem] leading-tight font-semibold tracking-[-0.02em]">{{ $profile->display_name }}</p>
            <p class="truncate text-[0.875rem] text-white/70">{{ $profile->commune?->name }}</p>
        </div>
    </div>

    <div class="mt-6 flex items-end justify-between gap-4 border-t border-white/10 px-6 pt-5 pb-6">
        <div>
            <p class="text-[0.6875rem] tracking-[0.14em] text-white/50 uppercase">{{ __('Numéro') }}</p>
            <p class="font-mono text-[1.0625rem] font-semibold tracking-wider">{{ $profile->number }}</p>
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-tile-green/20 px-2.5 py-1 text-[0.75rem] font-semibold text-[#4ade80]">
                <span class="size-1.5 rounded-full bg-current"></span>{{ $profile->status->label() }}
            </p>
        </div>
        <div class="rounded-xl bg-white p-2 [&_svg]:size-24">{{ BadgeQrCode::svg($profile, 120) }}</div>
    </div>
</article>
