<?php

namespace App\Http\Controllers\Public;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Territoire\Models\Commune;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Pages publiques : carte du réseau (WEB-06), mentions légales et cookies (WEB-07), plan du site (WEB-08).
 */
class PublicPagesController extends Controller
{
    /**
     * Nombre de Relais actifs par commune : aucune donnée personnelle, aucune position (§9.4).
     */
    public function network(): View
    {
        $counts = DB::table('relais_profiles')->where('status', RelaisStatus::Active->value)
            ->selectRaw('commune_id, count(*) as total')->groupBy('commune_id')->pluck('total', 'commune_id');

        $communes = Commune::query()->active()->with('region:id,name')->orderBy('name')->get(['id', 'name', 'region_id'])
            ->map(fn (Commune $commune): array => ['name' => $commune->name, 'region' => $commune->region?->name, 'relais' => (int) ($counts[$commune->id] ?? 0)]);

        return view('public.network', [
            'communes' => $communes,
            'total' => (int) $counts->sum(),
            'broadcast' => RelaisItem::withoutCommuneRestriction()->where('state', 'broadcast')->count(),
        ]);
    }

    public function legalNotice(): View
    {
        return view('public.mentions-legales');
    }

    public function cookies(): View
    {
        return view('public.cookies');
    }

    public function sitemap(): Response
    {
        $urls = [
            route('home'), route('relais.join'), route('showcase'), route('network'), route('contact'),
            route('legal-notice'), route('cookies'),
            ...array_map(fn (LegalDocumentType $type): string => route('legal.show', $type), LegalDocumentType::cases()),
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', array_map(fn (string $url): string => '<url><loc>'.e($url).'</loc></url>', $urls))
            .'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
