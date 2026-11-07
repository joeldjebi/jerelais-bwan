<?php

namespace App\Http\Requests\Api\V1\Candidature;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Support\StepRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'une étape du dossier (MOB-02). Champs facultatifs : brouillon sauvegardé au fil de l'eau.
 */
class UpdateCandidatureStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermissionTo(Permission::CandidatureApply->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(StepRules $rules): array
    {
        /** @var User $user */
        $user = $this->user();

        $communeUuid = $this->has('commune')
            ? $this->string('commune')->toString()
            : $user->candidature?->commune?->uuid;

        return $rules->for($this->step(), $communeUuid);
    }

    public function step(): CandidatureStep
    {
        $step = $this->route('step');

        return $step instanceof CandidatureStep ? $step : CandidatureStep::from((string) $step);
    }
}
