<?php

namespace App\Http\Controllers\Public;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Enums\ShowcaseVisual;
use App\Domain\Editorial\Support\ShowcaseVisuals;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vitrine publique « Vu sur REFLET TV » (WEB-05) : relais diffusés publiés par la rédaction.
 */
class ShowcaseController extends Controller
{
    public function index(): View
    {
        return view('public.showcase', [
            'items' => self::query()->paginate(12),
        ]);
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
     * @return Builder<RelaisItem>
     */
    public static function query(): Builder
    {
        return RelaisItem::withoutCommuneRestriction()
            ->whereNotNull('showcased_at')
            ->with(['commune:id,name', 'category:id,name', 'relaisProfile.candidature'])
            ->latest('showcased_at');
    }
}
