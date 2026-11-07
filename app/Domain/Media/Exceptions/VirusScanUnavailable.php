<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * L'antivirus ne répond pas : le fichier est refusé plutôt qu'accepté sans contrôle.
 */
class VirusScanUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Analyse antivirus impossible : {$reason}");
    }
}
