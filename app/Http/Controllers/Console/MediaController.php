<?php

namespace App\Http\Controllers\Console;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Lecture d'un média par l'équipe (COO-11) : droits vérifiés (commune, rôle), puis lien signé Wasabi de courte durée.
 * Le fichier ne transite pas par le serveur ; l'original n'est jamais modifié.
 */
class MediaController extends Controller
{
    public function __invoke(Media $media, ?string $variant = null): Response
    {
        $relais = RelaisItem::query()->find($media->relais_item_id);
        abort_if($relais === null, 404);
        Gate::authorize('view', $relais);
        abort_if($media->purged_at !== null, 410, __('Fichier supprimé à l’échéance de la durée de conservation.'));

        $file = $variant === null ? $media : $media->variants()->where('kind', $variant)->firstOrFail();
        $disk = Storage::disk($file->disk);

        try {
            return new RedirectResponse($disk->temporaryUrl($file->path, now()->addMinutes((int) config('jerelais.media.view_url_minutes'))));
        } catch (Throwable) {
            // Disques sans URL signée (développement, tests) : lecture directe.
            return $disk->response($file->path, null, ['Content-Type' => $file->mime ?? $media->declared_mime, 'Cache-Control' => 'private, max-age=600']);
        }
    }
}
