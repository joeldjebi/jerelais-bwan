<?php

namespace App\Domain\Audit\Support;

use App\Domain\Audit\Models\AuditLog;

/**
 * Recalcule la chaîne d'empreintes du journal pour prouver qu'aucune ligne n'a été altérée ou retirée.
 */
class AuditChainVerifier
{
    /**
     * @return array{checked: int, broken_at: ?int}
     */
    public function verify(): array
    {
        $previousHash = null;
        $checked = 0;

        foreach (AuditLog::query()->orderBy('id')->lazyById(500) as $entry) {
            if ($entry->prev_hash !== $previousHash || $entry->hash !== AuditLog::computeHash($previousHash, $entry)) {
                return ['checked' => $checked, 'broken_at' => $entry->id];
            }

            $previousHash = $entry->hash;
            $checked++;
        }

        return ['checked' => $checked, 'broken_at' => null];
    }
}
