<?php

namespace App\Console\Commands;

use App\Domain\Audit\Support\AuditChainVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:verify')]
#[Description('Vérifie l’intégrité du journal d’audit (chaîne d’empreintes)')]
class AuditVerifyCommand extends Command
{
    public function handle(AuditChainVerifier $verifier): int
    {
        $result = $verifier->verify();

        if ($result['broken_at'] !== null) {
            $this->components->error("Chaîne rompue à la ligne {$result['broken_at']} (après {$result['checked']} lignes valides).");

            return self::FAILURE;
        }

        $this->components->info("Journal intègre : {$result['checked']} lignes vérifiées.");

        return self::SUCCESS;
    }
}
