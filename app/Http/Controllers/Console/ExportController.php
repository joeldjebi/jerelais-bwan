<?php

namespace App\Http\Controllers\Console;

use App\Domain\Access\Enums\Permission;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Actions\ExportRelaisMedias;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Editorial\Models\RelaisCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Téléchargements de la rédaction (ADM-11, ADM-12) : ZIP d'un relais ou d'une collection, fichier d'origine seul.
 */
class ExportController extends Controller
{
    public function relais(Request $request, RelaisItem $relais, ExportRelaisMedias $export): Response
    {
        $user = $this->authorizeExport($request);
        Gate::authorize('view', $relais);

        try {
            $zip = $export->zip([$relais], $user, $request->string('derogation')->toString() ?: null);
        } catch (EditorialRefused $exception) {
            return back()->with('toast', ['message' => $exception->getMessage(), 'tone' => 'error']);
        }

        return $this->download($zip, Str::slug($relais->title ?: 'relais').'.zip');
    }

    public function collection(Request $request, RelaisCollection $collection, ExportRelaisMedias $export): Response
    {
        $user = $this->authorizeExport($request);
        $items = $collection->relais()->get()->filter(fn (RelaisItem $relais): bool => $relais->isExploitable() && Gate::forUser($user)->allows('view', $relais));

        if ($items->isEmpty()) {
            return back()->with('toast', ['message' => __('Aucun relais validé à télécharger dans cette collection.'), 'tone' => 'error']);
        }

        return $this->download($export->zip($items, $user), Str::slug($collection->name).'.zip');
    }

    public function original(Request $request, Media $media, ExportRelaisMedias $export): Response
    {
        $user = $this->authorizeExport($request);
        $relais = RelaisItem::query()->find($media->relais_item_id);
        abort_if($relais === null, 404);
        Gate::authorize('view', $relais);
        abort_unless($media->status === MediaStatus::Ready, 404);

        try {
            $export->original($media, $user, $request->string('derogation')->toString() ?: null);
        } catch (EditorialRefused $exception) {
            return back()->with('toast', ['message' => $exception->getMessage(), 'tone' => 'error']);
        }

        $disk = Storage::disk($media->disk);
        $name = Str::slug($relais->title ?: 'relais').'-'.$media->kind->value.'.'.$media->kind->extensionFor((string) $media->mime);

        try {
            return new RedirectResponse($disk->temporaryUrl($media->path, now()->addMinutes(10), [
                'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
            ]));
        } catch (Throwable) {
            return $disk->download($media->path, $name);
        }
    }

    private function authorizeExport(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasPermissionTo(Permission::MediasDownloadOriginal->value), 403);

        return $user;
    }

    private function download(string $path, string $name): BinaryFileResponse
    {
        return response()->download($path, $name, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
