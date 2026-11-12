<?php

namespace App\Http\Requests\Concerns;

use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Support\StepRules;
use Carbon\CarbonImmutable;

/**
 * Identité facultative (nom, prénoms, genre, date de naissance), règles de l'étape « identité » de la candidature.
 * La date de naissance est acceptée au format JJ/MM/AAAA (« 26/04/2000 ») ou AAAA-MM-JJ.
 */
trait DescribesIdentity
{
    public const array IDENTITY_FIELDS = ['last_name', 'first_names', 'gender', 'birth_date'];

    /**
     * Convertit une date JJ/MM/AAAA en AAAA-MM-JJ avant la validation ; une date invalide est laissée telle quelle
     * pour être refusée par la règle de format.
     */
    protected function normalizeBirthDate(): void
    {
        $value = $this->input('birth_date');

        if (is_string($value) && preg_match('#^\s*(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})\s*$#', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])) {
            $this->merge(['birth_date' => CarbonImmutable::createFromDate((int) $parts[3], (int) $parts[2], (int) $parts[1])->toDateString()]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function identityRules(): array
    {
        $identity = app(StepRules::class)->for(CandidatureStep::Identite);
        $rules = [];

        foreach (self::IDENTITY_FIELDS as $field) {
            $rules[$field] = array_values($identity[$field]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function identityAttributes(): array
    {
        return [
            'last_name' => __('nom'),
            'first_names' => __('prénoms'),
            'gender' => __('genre'),
            'birth_date' => __('date de naissance'),
        ];
    }

    /**
     * Champs d'identité envoyés et non vides (les absents ne sont pas modifiés).
     *
     * @return array<string, string>
     */
    public function identity(): array
    {
        $identity = [];

        foreach (self::IDENTITY_FIELDS as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) !== '') {
                $identity[$field] = trim($value);
            }
        }

        return $identity;
    }
}
