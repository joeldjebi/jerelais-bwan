<?php

namespace App\Domain\Candidature\Data;

use App\Domain\Candidature\Models\Candidature;

/**
 * Dossier qui partage un élément avec celui examiné (COO-03).
 */
final readonly class DuplicateMatch
{
    /**
     * @param  'document'|'identity'|'email'|'device'  $reason
     */
    public function __construct(
        public string $reason,
        public Candidature $candidature,
    ) {}

    public function label(): string
    {
        return __("candidature.duplicates.{$this->reason}");
    }
}
