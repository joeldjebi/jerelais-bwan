<?php

namespace App\Domain\Media\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lien signé de courte durée vers un fichier du stockage privé (Wasabi) : l'application l'affiche directement,
 * sans en-tête d'authentification. Null si le disque ne sait pas signer (ou si la signature échoue).
 */
final class SignedFileUrl
{
    public static function for(string $disk, string $path, int $minutes): ?string
    {
        try {
            return Storage::disk($disk)->temporaryUrl($path, CarbonImmutable::now()->addMinutes($minutes));
        } catch (Throwable) {
            return null;
        }
    }

    public static function expiresAt(int $minutes): string
    {
        return CarbonImmutable::now()->addMinutes($minutes)->toIso8601ZuluString();
    }
}
