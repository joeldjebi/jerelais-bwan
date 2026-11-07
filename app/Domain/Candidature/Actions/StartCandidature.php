<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Candidature\Models\Candidature;
use App\Models\User;

/**
 * Ouvre le dossier du candidat au premier accès (un seul dossier par compte).
 */
class StartCandidature
{
    public function handle(User $user): Candidature
    {
        $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->first();

        if ($candidature === null) {
            $candidature = new Candidature;
            $candidature->user()->associate($user);
            $candidature->save();
        }

        return $candidature->setRelation('user', $user);
    }
}
