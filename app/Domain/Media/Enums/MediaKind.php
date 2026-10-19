<?php

namespace App\Domain\Media\Enums;

/**
 * Nature d'un média (MOB-11) et formats acceptés, contrôlés sur le contenu du fichier (§10).
 */
enum MediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Audio = 'audio';

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Photo => ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
            self::Video => ['video/mp4', 'video/quicktime'],
            self::Audio => ['audio/mp4', 'audio/x-m4a', 'audio/mpeg', 'audio/aac', 'audio/x-hx-aac-adts'],
        };
    }

    /**
     * Paramètre de durée maximale (MOB-11) ; aucun pour les photos.
     */
    public function maxDurationSetting(): ?string
    {
        return match ($this) {
            self::Photo => null,
            self::Video => 'media.video_max_seconds',
            self::Audio => 'media.audio_max_seconds',
        };
    }

    public function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/heic', 'image/heif' => 'heic',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/aac', 'audio/x-hx-aac-adts' => 'aac',
            default => 'm4a',
        };
    }
}
