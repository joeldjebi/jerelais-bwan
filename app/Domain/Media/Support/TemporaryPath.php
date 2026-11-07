<?php

namespace App\Domain\Media\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Chemin de fichier temporaire au nom imprévisible, dans le répertoire privé de l'application.
 */
class TemporaryPath
{
    public static function make(string $extension = 'tmp'): string
    {
        $directory = storage_path('app/private/tmp-media');
        File::ensureDirectoryExists($directory, 0700);

        return $directory.'/'.Str::uuid7().'.'.ltrim($extension, '.');
    }
}
