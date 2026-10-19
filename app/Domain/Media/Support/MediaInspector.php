<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaKind;
use Carbon\CarbonImmutable;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use GdImage;
use Throwable;

/**
 * Lecture technique d'un fichier reçu : type réel, métadonnées d'origine (MOB-12, MOB-13),
 * vignette ré-encodée sans EXIF et empreinte perceptuelle (COO-13).
 */
class MediaInspector
{
    public const THUMBNAIL_WIDTH = 640;

    public function mimeOf(string $path): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    /**
     * Le contenu correspond au type de média annoncé. Un M4A peut être reconnu comme « video/mp4 ».
     */
    public function matchesKind(MediaKind $kind, string $mime): bool
    {
        return in_array($mime, $kind->mimeTypes(), true)
            || ($kind === MediaKind::Audio && $mime === 'video/mp4');
    }

    /**
     * @return array{captured_at: CarbonImmutable|null, duration_s: float|null, width: int|null, height: int|null, metadata: array<string, mixed>}
     */
    public function metadata(MediaKind $kind, string $path, string $mime): array
    {
        return $kind === MediaKind::Photo && in_array($mime, ['image/jpeg', 'image/png'], true)
            ? $this->imageMetadata($path, $mime)
            : $this->probeMetadata($path);
    }

    /**
     * Vignette JPEG ré-encodée (sans aucune métadonnée), orientée correctement. Null si impossible.
     */
    public function thumbnail(MediaKind $kind, string $path, string $mime, ?float $duration, string $target): ?GdImage
    {
        $image = match (true) {
            $kind === MediaKind::Photo && $mime === 'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            $kind === MediaKind::Photo && $mime === 'image/png' => @imagecreatefrompng($path) ?: null,
            $kind === MediaKind::Video, $kind === MediaKind::Photo => $this->frameWithFfmpeg($path, $kind === MediaKind::Video ? min(1.0, max(0.0, ($duration ?? 0) / 2)) : 0.0),
            default => null,
        };

        if (! $image instanceof GdImage) {
            return null;
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $path);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, self::THUMBNAIL_WIDTH / max(1, $width));
        $thumb = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);
        imagejpeg($thumb, $target, 82);

        return $thumb;
    }

    /**
     * @return array{captured_at: CarbonImmutable|null, duration_s: float|null, width: int|null, height: int|null, metadata: array<string, mixed>}
     */
    private function imageMetadata(string $path, string $mime): array
    {
        $size = @getimagesize($path) ?: [null, null];
        $exif = $mime === 'image/jpeg' ? (@exif_read_data($path) ?: []) : [];

        $taken = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        return [
            'captured_at' => is_string($taken) ? $this->parseDate($taken, 'Y:m:d H:i:s') : null,
            'duration_s' => null,
            'width' => is_int($size[0]) ? $size[0] : null,
            'height' => is_int($size[1]) ? $size[1] : null,
            'metadata' => array_filter([
                'device' => trim(((string) ($exif['Make'] ?? '')).' '.((string) ($exif['Model'] ?? ''))) ?: null,
                'orientation' => isset($exif['Orientation']) ? (int) $exif['Orientation'] : null,
                'software' => $exif['Software'] ?? null,
                'gps' => $this->exifGps($exif),
            ], fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @return array{captured_at: CarbonImmutable|null, duration_s: float|null, width: int|null, height: int|null, metadata: array<string, mixed>}
     */
    private function probeMetadata(string $path): array
    {
        try {
            $probe = FFProbe::create(['ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries')]);
            $format = $probe->format($path);
            $video = $probe->streams($path)->videos()->first();
        } catch (Throwable) {
            return ['captured_at' => null, 'duration_s' => null, 'width' => null, 'height' => null, 'metadata' => []];
        }

        /** @var array<string, string> $tags */
        $tags = $format->get('tags') ?? [];
        $created = $tags['creation_time'] ?? $tags['com.apple.quicktime.creationdate'] ?? null;
        $location = $tags['location'] ?? $tags['com.apple.quicktime.location.ISO6709'] ?? null;

        return [
            'captured_at' => is_string($created) ? $this->parseDate($created) : null,
            'duration_s' => is_numeric($format->get('duration')) ? round((float) $format->get('duration'), 2) : null,
            'width' => $video?->get('width') !== null ? (int) $video->get('width') : null,
            'height' => $video?->get('height') !== null ? (int) $video->get('height') : null,
            'metadata' => array_filter([
                'device' => $tags['com.apple.quicktime.model'] ?? $tags['model'] ?? null,
                'encoder' => $tags['encoder'] ?? null,
                'gps' => is_string($location) ? $this->iso6709($location) : null,
            ], fn (mixed $value): bool => $value !== null),
        ];
    }

    private function frameWithFfmpeg(string $path, float $second): ?GdImage
    {
        $frame = TemporaryPath::make('jpg');

        try {
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('laravel-ffmpeg.ffmpeg.binaries'),
                'ffprobe.binaries' => config('laravel-ffmpeg.ffprobe.binaries'),
                'timeout' => 120,
            ]);
            $media = $ffmpeg->open($path);

            if (! $media instanceof Video) {
                return null;
            }

            $media->frame(TimeCode::fromSeconds($second))->save($frame);

            return @imagecreatefromjpeg($frame) ?: null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($frame);
        }
    }

    private function orient(GdImage $image, string $path): GdImage
    {
        $orientation = (int) ((@exif_read_data($path) ?: [])['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * @param  array<string, mixed>  $exif
     * @return array{lat: float, lng: float}|null
     */
    private function exifGps(array $exif): ?array
    {
        if (! isset($exif['GPSLatitude'], $exif['GPSLongitude']) || ! is_array($exif['GPSLatitude']) || ! is_array($exif['GPSLongitude'])) {
            return null;
        }

        $toDecimal = function (array $parts): float {
            $values = array_map(function (mixed $part): float {
                [$num, $den] = array_pad(explode('/', (string) $part), 2, '1');

                return (float) $den === 0.0 ? 0.0 : (float) $num / (float) $den;
            }, $parts);

            return ($values[0] ?? 0) + ($values[1] ?? 0) / 60 + ($values[2] ?? 0) / 3600;
        };

        $lat = $toDecimal($exif['GPSLatitude']) * (($exif['GPSLatitudeRef'] ?? 'N') === 'S' ? -1 : 1);
        $lng = $toDecimal($exif['GPSLongitude']) * (($exif['GPSLongitudeRef'] ?? 'E') === 'W' ? -1 : 1);

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function iso6709(string $value): ?array
    {
        return preg_match('/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)/', $value, $matches) === 1
            ? ['lat' => (float) $matches[1], 'lng' => (float) $matches[2]]
            : null;
    }

    private function parseDate(string $value, ?string $format = null): ?CarbonImmutable
    {
        try {
            $date = $format === null ? CarbonImmutable::parse($value) : CarbonImmutable::createFromFormat($format, $value);

            return $date instanceof CarbonImmutable ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }
}
