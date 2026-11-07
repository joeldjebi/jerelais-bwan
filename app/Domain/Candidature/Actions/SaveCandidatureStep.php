<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\AgeRequirement;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use Carbon\CarbonImmutable;

/**
 * Enregistre une étape du formulaire (sauvegarde automatique, MOB-02).
 * Les données doivent avoir été validées avec StepRules.
 */
class SaveCandidatureStep
{
    public function __construct(private readonly AgeRequirement $age) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Candidature $candidature, CandidatureStep $step, array $data): Candidature
    {
        if (! $candidature->status->isEditable()) {
            throw CandidatureLocked::make();
        }

        $data = array_intersect_key($data, array_flip($step->fields()));

        match ($step) {
            CandidatureStep::Identite => $this->fillIdentity($candidature, $data),
            CandidatureStep::Territoire => $this->fillTerritory($candidature, $data),
            CandidatureStep::Profil => $candidature->fill($data),
        };

        $candidature->save();

        return $candidature;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillIdentity(Candidature $candidature, array $data): void
    {
        $candidature->fill($data);

        if (array_key_exists('birth_date', $data)) {
            $birthDate = is_string($data['birth_date']) ? CarbonImmutable::createFromFormat('Y-m-d', $data['birth_date']) : null;
            $candidature->is_minor = $birthDate instanceof CarbonImmutable && $this->age->isMinor($birthDate);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillTerritory(Candidature $candidature, array $data): void
    {
        if (array_key_exists('commune', $data)) {
            $communeId = $this->idFor(Commune::class, $data['commune']);

            if ($communeId !== $candidature->commune_id) {
                $candidature->localite_id = null;
            }

            $candidature->commune_id = $communeId;
        }

        if (array_key_exists('localite', $data)) {
            $candidature->localite_id = $this->idFor(Localite::class, $data['localite']);
        }

        if (array_key_exists('lieu_type', $data)) {
            $candidature->lieu_type_id = $this->idFor(LieuType::class, $data['lieu_type']);
        }
    }

    /**
     * @param  class-string<Commune|Localite|LieuType>  $model
     */
    private function idFor(string $model, mixed $uuid): ?int
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        $id = $model::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }
}
