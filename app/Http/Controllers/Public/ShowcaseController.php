<?php

namespace App\Http\Controllers\Public;

use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Enums\ShowcaseVisual;
use App\Domain\Editorial\Support\ShowcaseVisuals;
use App\Domain\Territoire\Models\Commune;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vitrine publique « Vu sur REFLET TV » (WEB-05) : relais diffusés publiés par la rédaction.
 */
class ShowcaseController extends Controller
{
    /** Périodes proposées dans les filtres : durée écoulée depuis la diffusion. */
    public const PERIODS = ['semaine' => '7 days', 'mois' => '1 month', 'annee' => '1 year'];

    /**
     * Vitrine publique : 12 diffusions par page, filtres par recherche, commune, catégorie, période et ordre,
     * conservés dans l'adresse (partageable, sans JavaScript).
     */
    public function index(Request $request): View
    {
        $filters = self::filters($request);
        $items = self::query($filters)->paginate(12)->withQueryString();

        $published = RelaisItem::withoutCommuneRestriction()->whereNotNull('showcased_at');

        return view('public.showcase', [
            'items' => $items,
            'filters' => $filters,
            'total' => (clone $published)->count(),
            'communes' => Commune::query()->whereIn('id', (clone $published)->select('commune_id'))->orderBy('name')->get(['id', 'name', 'slug']),
            'categories' => Category::query()->whereIn('id', (clone $published)->select('category_id'))->orderBy('position')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Filtres lus dans l'adresse ; une valeur inconnue est ignorée.
     *
     * @return array{q: string, commune: string, categorie: string, periode: string, tri: string}
     */
    public static function filters(Request $request): array
    {
        $text = fn (string $key, int $max = 80): string => mb_substr(trim($request->string($key)->toString()), 0, $max);

        return [
            'q' => $text('q'),
            'commune' => $text('commune', 120),
            'categorie' => $text('categorie', 120),
            'periode' => in_array($request->string('periode')->toString(), array_keys(self::PERIODS), true) ? $request->string('periode')->toString() : '',
            'tri' => $request->string('tri')->toString() === 'anciens' ? 'anciens' : 'recents',
        ];
    }

    /**
     * Image publique selon le visuel choisi par la rédaction : vignette d'origine, vignette floutée ou image de la
     * version floutée. Jamais l'original, jamais d'EXIF.
     */
    public function image(RelaisItem $relais, ShowcaseVisuals $visuals): StreamedResponse
    {
        $image = $visuals->publicImage($relais);
        abort_if($image === null, 404);

        return Storage::disk($image['disk'])->response($image['path'], null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Adresse de l'image publique, seulement si elle existe : jamais d'image cassée sur le site ni dans l'application.
     */
    public static function imageUrl(RelaisItem $relais): ?string
    {
        return app(ShowcaseVisuals::class)->publicImage($relais) === null ? null : route('showcase.image', $relais);
    }

    /**
     * Vidéo floutée par la rédaction (visuel « edited ») : lien signé de courte durée vers le stockage.
     */
    public function video(RelaisItem $relais): RedirectResponse
    {
        abort_unless($relais->showcased_at !== null && $relais->showcase_visual === ShowcaseVisual::Edited && self::hasVideo($relais), 404);

        return new RedirectResponse(Storage::disk(ShowcaseVisuals::DISK)->temporaryUrl((string) $relais->showcase_media_path, now()->addMinutes(30)));
    }

    public static function hasVideo(RelaisItem $relais): bool
    {
        return $relais->showcase_visual === ShowcaseVisual::Edited && str_starts_with((string) $relais->showcase_media_mime, 'video/');
    }

    /**
     * Relais publiés sur la vitrine, filtrés (site public et GET /diffusions).
     *
     * @param  array{q?: string, commune?: string, categorie?: string, periode?: string, tri?: string}  $filters
     * @return Builder<RelaisItem>
     */
    public static function query(array $filters = []): Builder
    {
        $q = $filters['q'] ?? '';
        $commune = $filters['commune'] ?? '';
        $categorie = $filters['categorie'] ?? '';
        $period = self::PERIODS[$filters['periode'] ?? ''] ?? null;
        $since = $period === null ? null : now()->sub($period);
        $date = 'coalesce(relais_items.broadcast_at, relais_items.showcased_at)';

        return RelaisItem::withoutCommuneRestriction()
            ->whereNotNull('showcased_at')
            ->with(['commune:id,name,slug', 'category:id,name,slug', 'relaisProfile.candidature'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('showcase_title', 'ilike', '%'.$q.'%')
                ->orWhere('showcase_text', 'ilike', '%'.$q.'%')
                ->orWhere('broadcast_programme', 'ilike', '%'.$q.'%')))
            ->when($commune !== '', fn (Builder $query) => $query->whereHas('commune', fn (Builder $inner) => $inner->where('slug', $commune)))
            ->when($categorie !== '', fn (Builder $query) => $query->whereHas('category', fn (Builder $inner) => $inner->where('slug', $categorie)))
            ->when($since !== null, fn (Builder $query) => $query->whereRaw("{$date} >= ?", [$since]))
            ->orderByRaw($date.(($filters['tri'] ?? 'recents') === 'anciens' ? ' asc' : ' desc'))
            ->orderBy('relais_items.id', ($filters['tri'] ?? 'recents') === 'anciens' ? 'asc' : 'desc');
    }
}
