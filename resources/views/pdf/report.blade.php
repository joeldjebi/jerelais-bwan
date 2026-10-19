<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Rapport de pilotage') }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1d1d1f; font-size: 10.5px; line-height: 1.45; }
        .brand { font-size: 9.5px; letter-spacing: 2px; text-transform: uppercase; color: #f28c28; font-weight: bold; }
        h1 { font-size: 22px; margin: 6px 0 2px; letter-spacing: -0.5px; }
        .meta { color: #6e6e73; margin-bottom: 18px; }
        h2 { font-size: 14px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #f28c28; }
        h3 { font-size: 11px; margin: 12px 0 4px; color: #3a3a3c; }
        .figures { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
        .figure { background: #f5f5f7; border-radius: 8px; padding: 8px 10px; width: 33%; vertical-align: top; }
        .figure .value { font-size: 17px; font-weight: bold; }
        .figure .label { color: #6e6e73; font-size: 9px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; color: #6e6e73; font-weight: normal; border-bottom: 1px solid #d2d2d7; padding: 4px 6px 4px 0; }
        table.data td { border-bottom: 1px solid #ececf0; padding: 4px 6px 4px 0; }
        table.data td.num, table.data th.num { text-align: right; }
        .small { color: #6e6e73; font-size: 8.5px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="brand">JE RELAIS · REFLET TV</div>
    <h1>{{ __('Rapport de pilotage') }}</h1>
    <div class="meta">{{ $period }} · {{ $scope }}</div>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="figures">
            @foreach (array_chunk($section['figures'], 3) as $row)
                <tr>
                    @foreach ($row as $figure)
                        <td class="figure"><div class="value">{{ $figure['value'] ?? '—' }}</div><div class="label">{{ $figure['label'] }}</div></td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        @foreach ($section['tables'] as $table)
            @continue($table['rows'] === [])
            <h3>{{ $table['title'] }}</h3>
            <table class="data">
                <tr>@foreach ($table['columns'] as $index => $column)<th @class(['num' => $index > 0])>{{ $column }}</th>@endforeach</tr>
                @foreach (array_slice($table['rows'], 0, 31) as $row)
                    <tr>@foreach ($row as $index => $cell)<td @class(['num' => $index > 0])>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </table>
        @endforeach
    @endforeach

    <p class="small">{{ __('Généré le :date. Données agrégées, sans information personnelle sensible.', ['date' => now()->timezone('Africa/Abidjan')->translatedFormat('j F Y à H:i')]) }}</p>
</body>
</html>
