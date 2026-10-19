@php
    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $relais->title }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1d1d1f; font-size: 11px; line-height: 1.5; }
        .brand { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #f28c28; font-weight: bold; }
        h1 { font-size: 22px; margin: 6px 0 4px; letter-spacing: -0.5px; }
        .meta { color: #6e6e73; }
        .alert { margin: 14px 0; padding: 10px 12px; border-radius: 8px; background: #fff1f0; color: #b3261e; font-weight: bold; }
        .ok { background: #eefaf1; color: #1e7b34; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { text-align: left; width: 32%; color: #6e6e73; font-weight: normal; padding: 6px 8px 6px 0; vertical-align: top; border-bottom: 1px solid #ececf0; }
        td { padding: 6px 0; vertical-align: top; border-bottom: 1px solid #ececf0; }
        h2 { font-size: 13px; margin: 20px 0 6px; }
        .small { font-size: 9px; color: #6e6e73; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px; }
    </style>
</head>
<body>
    <div class="brand">JE RELAIS · REFLET TV · {{ __('Fiche d’accompagnement') }}</div>
    <h1>{{ $relais->title ?: __('Sans titre') }}</h1>
    <div class="meta">{{ collect([$relais->category?->name, $relais->commune?->name, $relais->urgency->label()])->filter()->join(' · ') }}</div>

    @if ($relais->blur_required || $relais->has_minors)
        <div class="alert">{{ __('Consigne : floutage nécessaire avant diffusion.') }}@if ($relais->has_minors) {{ __('Présence de mineurs identifiables.') }}@endif</div>
    @else
        <div class="alert ok">{{ __('Aucun floutage demandé par le coordonnateur.') }}</div>
    @endif

    <table>
        <tr><th>{{ __('Crédit à afficher') }}</th><td><strong>{{ $credit }}</strong></td></tr>
        <tr><th>{{ __('Description') }}</th><td>{{ $relais->description ?: '—' }}</td></tr>
        <tr><th>{{ __('Lieu') }}</th><td>{{ collect([$relais->declared_place, $relais->commune?->name])->filter()->join(', ') ?: '—' }}@if ($relais->location)<br><span class="small">GPS {{ number_format($relais->location->getLatitude(), 5) }}, {{ number_format($relais->location->getLongitude(), 5) }}@if ($relais->location_accuracy_m) (± {{ $relais->location_accuracy_m }} m)@endif</span>@endif</td></tr>
        <tr><th>{{ __('Prise de vue') }}</th><td>{{ $date($relais->medias->pluck('captured_at')->filter()->min() ?? $relais->device_captured_at) ?? '—' }}</td></tr>
        <tr><th>{{ __('Reçu') }}</th><td>{{ $date($relais->received_at) }}</td></tr>
        <tr><th>{{ __('Relais') }}</th><td>{{ $relais->relaisProfile?->display_name }} · {{ $relais->relaisProfile?->number }}</td></tr>
        <tr><th>{{ __('Accord des personnes filmées') }}</th><td>{{ $relais->people_consent?->label() ?? '—' }}</td></tr>
        <tr><th>{{ __('Validé par') }}</th><td>{{ $validator?->name ?? '—' }}</td></tr>
        @if ($relais->programme)<tr><th>{{ __('Émission prévue') }}</th><td>{{ $relais->programme }}</td></tr>@endif
    </table>

    <h2>{{ __('Fichiers d’origine') }}</h2>
    <table>
        @foreach ($relais->medias->where('status', \App\Domain\Media\Enums\MediaStatus::Ready)->values() as $index => $media)
            <tr>
                <th>{{ sprintf('%02d', $index + 1) }} · {{ ucfirst($media->kind->value) }}@if ($media->is_imported) · {{ __('importé') }}@endif</th>
                <td>{{ $media->size >= 1048576 ? number_format($media->size / 1048576, 1, ',', ' ').' Mo' : max(1, (int) round($media->size / 1024)).' Ko' }} @if ($media->duration_s) · {{ (int) $media->duration_s }} s @endif<br><span class="mono">SHA-256 {{ $media->sha256 }}</span></td>
            </tr>
        @endforeach
    </table>

    <p class="small" style="margin-top: 24px">{{ __('Document généré le :date. La rédaction reste seule juge de la diffusion.', ['date' => $date(now())]) }}</p>
</body>
</html>
