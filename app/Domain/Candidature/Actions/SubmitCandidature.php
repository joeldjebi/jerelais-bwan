<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Events\CandidatureSubmitted;
use App\Domain\Candidature\Exceptions\CandidatureIncomplete;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\CandidatureChecklist;
use Illuminate\Support\Facades\DB;

/**
 * Dépôt du dossier complet (MOB-06 : « reçue »), y compris après une demande de complément.
 */
class SubmitCandidature
{
    public function __construct(
        private readonly CandidatureChecklist $checklist,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Candidature $candidature): Candidature
    {
        if (! $candidature->status->isEditable()) {
            throw CandidatureLocked::make();
        }

        $missing = $this->checklist->missing($candidature);

        if ($missing !== []) {
            throw new CandidatureIncomplete($missing);
        }

        $isResubmission = $candidature->status === CandidatureStatus::ComplementRequested;

        DB::transaction(function () use ($candidature, $isResubmission): void {
            $candidature->forceFill([
                'status' => CandidatureStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            $this->audit->record(
                $isResubmission ? 'candidature.resubmitted' : 'candidature.submitted',
                $candidature,
                actor: $candidature->user,
                communeId: $candidature->commune_id,
            );
        });

        CandidatureSubmitted::dispatch($candidature, $isResubmission);

        return $candidature;
    }
}
