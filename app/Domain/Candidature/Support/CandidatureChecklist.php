<?php

namespace App\Domain\Candidature\Support;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Legal\Actions\AcceptLegalDocuments;

/**
 * Ce qui doit être complet avant le dépôt du dossier.
 * La formation s'y ajoute au fil de l'étape 2.
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

        $items['documents'] = [
            'label' => __('candidature.steps.documents'),
            'complete' => $this->missingDocuments($candidature) === [],
        ];

        $items['charte'] = [
            'label' => __('candidature.steps.charte'),
            'complete' => AcceptLegalDocuments::isUpToDate($candidature->user),
        ];

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

    /**
     * Pièces attendues et pas encore fournies.
     *
     * @return list<DocumentType>
     */
    public function missingDocuments(Candidature $candidature): array
    {
        $provided = $candidature->documents()->pluck('type')->map(fn (mixed $type): string => $type instanceof DocumentType ? $type->value : (string) $type)->all();

        return array_values(array_filter(
            DocumentType::requiredFor($candidature),
            fn (DocumentType $type): bool => ! in_array($type->value, $provided, true),
        ));
    }

    public function isComplete(Candidature $candidature): bool
    {
        return $this->missing($candidature) === [];
    }
}
