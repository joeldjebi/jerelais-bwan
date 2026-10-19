<?php

namespace App\Domain\Media\Antivirus;

use App\Domain\Media\Contracts\VirusScanner;

/**
 * Antivirus de développement et de test : tout fichier est sain, sauf la signature de test EICAR.
 * Refusé en production (voir DomainServiceProvider).
 */
class FakeVirusScanner implements VirusScanner
{
    public const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function scan(string $path): ?string
    {
        $content = (string) @file_get_contents($path, length: 4096);

        return str_contains($content, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') ? 'Eicar-Test-Signature' : null;
    }
}
