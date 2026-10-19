<?php

namespace App\Domain\Candidature\Support;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Models\Candidature;

/**
 * Ce qui doit être complet avant le dépôt du dossier.
 * Les pièces, la charte et la formation s'y ajoutent au fil de l'étape 2.
 */
class CandidatureChecklist
{
    public const MOTIVATION_MIN_LENGTH = 30;

    public function __construct(private readonly AgeRequirement $age) {}

    /**
     * @return array<string, array{label: string, complete: bool}>
     */
    public function items(Candidature $candidature): array
    {
        $items = [];

        foreach (CandidatureStep::cases() as $step) {
            $items[$step->value] = [
                'label' => $step->label(),
                'complete' => $candidature->isStepComplete($step),
            ];
        }

        return $items;
    }

    /**
     * Éléments manquants ou invalides, avec le message à afficher.
     *
     * @return array<string, string>
     */
    public function missing(Candidature $candidature): array
    {
        $missing = [];

        foreach ($this->items($candidature) as $key => $item) {
            if (! $item['complete']) {
                $missing[$key] = __('candidature.step_incomplete', ['step' => $item['label']]);
            }
        }

        if ($candidature->birth_date !== null && ! $this->age->isEligible($candidature->birth_date)) {
            $missing['birth_date'] = $this->age->refusalMessage();
        }

        if ($candidature->motivation !== null && mb_strlen(trim($candidature->motivation)) < self::MOTIVATION_MIN_LENGTH) {
            $missing['motivation'] = __('candidature.motivation_too_short', ['min' => self::MOTIVATION_MIN_LENGTH]);
        }

        return $missing;
    }

    public function isComplete(Candidature $candidature): bool
    {
        return $this->missing($candidature) === [];
    }
}
