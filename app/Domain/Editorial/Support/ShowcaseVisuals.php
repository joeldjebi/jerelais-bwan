<?php

namespace App\Domain\Editorial\Support;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Enums\ShowcaseVisual;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Media\Support\MediaInspector;
use App\Domain\Media\Support\TemporaryPath;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Visuels publics d'un relais (WEB-05, ADM-17), toujours distincts des originaux qui restent privés :
 * vignette floutée en entier par le serveur, ou version floutée déposée par la rédaction (photo ou vidéo)
 * avec son image publique ré-encodée (aucune métadonnée).
 */
class ShowcaseVisuals
{
    public const DISK = 'medias';

    /** Taille maximale d'une version floutée déposée, en mégaoctets. */
    public const EDITED_MAX_MB = 100;

    public function __construct(
        private readonly MediaInspector $inspector,
        private readonly VirusScanner $scanner,
    ) {}

    /**
     * Vignette floutée : réduite à quelques pixels puis agrandie et adoucie, aucun visage n'y est reconnaissable.
     *
     * @return string chemin sur le disque « medias »
     *
     * @throws EditorialRefused aucune image contrôlée pour ce relais
     */
    public function blurred(RelaisItem $relais): string
    {
        if ($relais->showcase_blurred_path !== null && Storage::disk(self::DISK)->exists($relais->showcase_blurred_path)) {
            return $relais->showcase_blurred_path;
        }

        $variant = $this->thumbnail($relais);
        $source = @imagecreatefromstring((string) Storage::disk($variant->disk)->get($variant->path));

        if (! $source instanceof GdImage) {
            throw new EditorialRefused(__('editorial.errors.showcase_no_image'));
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $tiny = imagecreatetruecolor(max(1, intdiv($width, 24)), max(1, intdiv($height, 24)));
        imagecopyresampled($tiny, $source, 0, 0, 0, 0, imagesx($tiny), imagesy($tiny), $width, $height);
        $blurred = imagecreatetruecolor($width, $height);
        imagecopyresampled($blurred, $tiny, 0, 0, 0, 0, $width, $height, imagesx($tiny), imagesy($tiny));

        for ($pass = 0; $pass < 8; $pass++) {
            imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $path = 'vitrine/'.$relais->uuid.'/floute-'.Str::lower(Str::random(8)).'.jpg';
        Storage::disk(self::DISK)->put($path, $this->jpeg($blurred));

        return $path;
    }

    /**
     * Version floutée déposée par la rédaction : contrôlée (type réel, antivirus), stockée à part, image publique
     * ré-encodée (photo réduite, ou image extraite de la vidéo).
     *
     * @return array{path: string, mime: string, poster: string}
     *
     * @throws EditorialRefused format refusé ou fichier infecté
     */
    public function storeEdited(RelaisItem $relais, UploadedFile $file): array
    {
        $local = (string) $file->getRealPath();
        $mime = $this->inspector->mimeOf($local);
        $kind = MediaKind::fromMime($mime);

        if (! in_array($kind, [MediaKind::Photo, MediaKind::Video], true) || in_array($mime, ['image/heic', 'image/heif'], true)) {
            throw new EditorialRefused(__('editorial.errors.showcase_edited_type'));
        }

        if ($this->scanner->scan($local) !== null) {
            throw new EditorialRefused(__('editorial.errors.showcase_edited_infected'));
        }

        $poster = TemporaryPath::make('jpg');

        try {
            $image = $kind === MediaKind::Photo ? $this->resizedPhoto($local) : $this->inspector->thumbnail(MediaKind::Video, $local, $mime, $this->inspector->metadata(MediaKind::Video, $local, $mime)['duration_s'], $poster);

            if (! $image instanceof GdImage) {
                throw new EditorialRefused(__('editorial.errors.showcase_edited_type'));
            }

            $base = 'vitrine/'.$relais->uuid.'/'.Str::lower(Str::random(8));
            $path = $base.'.'.$kind->extensionFor($mime);
            Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));
            Storage::disk(self::DISK)->put($base.'-affiche.jpg', $this->jpeg($image));
        } finally {
            @unlink($poster);
        }

        return ['path' => $path, 'mime' => $mime, 'poster' => $base.'-affiche.jpg'];
    }

    public function hasThumbnail(RelaisItem $relais): bool
    {
        return $this->thumbnailOrNull($relais) !== null;
    }

    /**
     * @throws EditorialRefused
     */
    private function thumbnail(RelaisItem $relais): MediaVariant
    {
        return $this->thumbnailOrNull($relais) ?? throw new EditorialRefused(__('editorial.errors.showcase_no_image'));
    }

    /**
     * Vignette publique d'origine, toujours la même : celle de la première photo contrôlée, sinon de la première
     * vidéo contrôlée. Null tant qu'aucun fichier n'a fini son contrôle.
     */
    public function thumbnailOrNull(RelaisItem $relais): ?MediaVariant
    {
        return MediaVariant::query()
            ->select('media_variants.*')
            ->join('medias', 'medias.id', '=', 'media_variants.media_id')
            ->where('media_variants.kind', 'thumbnail')
            ->where('medias.relais_item_id', $relais->id)
            ->where('medias.status', MediaStatus::Ready->value)
            ->whereNull('medias.purged_at')
            ->orderByRaw("case medias.kind when 'photo' then 0 else 1 end")
            ->orderBy('medias.id')
            ->first();
    }

    /**
     * Chemin de l'image publique selon le visuel publié, ou null si elle n'existe pas (encore).
     *
     * @return array{disk: string, path: string}|null
     */
    public function publicImage(RelaisItem $relais): ?array
    {
        $image = match ($relais->showcase_visual) {
            ShowcaseVisual::Original => ($variant = $this->thumbnailOrNull($relais)) === null ? null : ['disk' => $variant->disk, 'path' => $variant->path],
            ShowcaseVisual::Blurred => $relais->showcase_blurred_path === null ? null : ['disk' => self::DISK, 'path' => $relais->showcase_blurred_path],
            ShowcaseVisual::Edited => $relais->showcase_poster_path === null ? null : ['disk' => self::DISK, 'path' => $relais->showcase_poster_path],
            ShowcaseVisual::None => null,
        };

        return $relais->showcased_at === null ? null : $image;
    }

    private function resizedPhoto(string $path): ?GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            return null;
        }

        $ratio = min(1, 1280 / max(1, imagesx($image)));

        return $ratio < 1 ? (imagescale($image, (int) round(imagesx($image) * $ratio)) ?: $image) : $image;
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }
}
