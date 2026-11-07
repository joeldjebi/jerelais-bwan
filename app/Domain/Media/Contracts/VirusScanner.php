<?php

namespace App\Domain\Media\Contracts;

use App\Domain\Media\Exceptions\VirusScanUnavailable;

/**
 * Analyse antivirus d'un fichier reçu, avant tout stockage ou mise à disposition (§10).
 */
interface VirusScanner
{
    /**
     * @return string|null nom de la menace détectée, null si le fichier est sain
     *
     * @throws VirusScanUnavailable si l'analyse n'a pas pu être faite (le fichier est alors refusé)
     */
    public function scan(string $path): ?string;
}
