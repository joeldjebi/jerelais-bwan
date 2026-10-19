<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Auth\Models\Device;
use App\Domain\Candidature\Data\DuplicateMatch;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;

/**
 * Aide à la vérification (COO-03) : autres dossiers partageant la même pièce (empreinte SHA-256),
 * la même identité (nom, prénoms, date de naissance), le même e-mail ou le même appareil.
 * La recherche couvre toutes les communes (dossiers déposés uniquement) ; seul le strict nécessaire est affiché.
 * Le même numéro de téléphone est impossible : un numéro = un compte.
 */
class FindCandidatureDuplicates
{
    /**
     * @return list<DuplicateMatch>
     */
    public function handle(Candidature $candidature): array
    {
        $matches = [];
        $others = fn () => Candidature::withoutCommuneRestriction()->with('commune')->whereKeyNot($candidature->id)
            ->where('status', '!=', CandidatureStatus::Draft->value);

        $hashes = $candidature->documents()->pluck('sha256');

        if ($hashes->isNotEmpty()) {
            $ids = CandidatureDocument::query()->whereIn('sha256', $hashes)->where('candidature_id', '!=', $candidature->id)->pluck('candidature_id');

            foreach ($others()->whereIn('id', $ids)->get() as $other) {
                $matches[$other->id] = new DuplicateMatch('document', $other);
            }
        }

        if ($candidature->last_name !== null && $candidature->first_names !== null && $candidature->birth_date !== null) {
            $sameIdentity = $others()
                ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($candidature->last_name))])
                ->whereRaw('lower(first_names) = ?', [mb_strtolower(trim($candidature->first_names))])
                ->whereDate('birth_date', $candidature->birth_date->toDateString())
                ->get();

            foreach ($sameIdentity as $other) {
                $matches[$other->id] ??= new DuplicateMatch('identity', $other);
            }
        }

        if ($candidature->email !== null) {
            foreach ($others()->whereRaw('lower(email) = ?', [mb_strtolower(trim($candidature->email))])->get() as $other) {
                $matches[$other->id] ??= new DuplicateMatch('email', $other);
            }
        }

        $tokens = Device::query()->where('user_id', $candidature->user_id)->whereNotNull('fcm_token_hash')->pluck('fcm_token_hash');

        if ($tokens->isNotEmpty()) {
            $userIds = Device::query()->whereIn('fcm_token_hash', $tokens)->where('user_id', '!=', $candidature->user_id)->pluck('user_id');

            foreach ($others()->whereIn('user_id', $userIds)->get() as $other) {
                $matches[$other->id] ??= new DuplicateMatch('device', $other);
            }
        }

        return array_values($matches);
    }
}
