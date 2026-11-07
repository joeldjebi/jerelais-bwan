<?php

namespace App\Http\Controllers\Public;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
     * Vignette publique, uniquement pour un relais publié avec image (jamais l'original, jamais d'EXIF).
     */
    public function image(RelaisItem $relais): StreamedResponse
    {
        abort_unless($relais->showcased_at !== null && $relais->showcase_with_image, 404);

        $variant = $relais->medias()->where('status', MediaStatus::Ready->value)->with('variants')->get()
            ->flatMap->variants->firstWhere('kind', 'thumbnail');
        abort_if($variant === null, 404);

        return Storage::disk($variant->disk)->response($variant->path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
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
