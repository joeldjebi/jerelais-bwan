<?php

namespace App\Domain\Candidature\Support;

use App\Domain\Settings\Settings;
use DateTimeInterface;

/**
 * Règle d'âge des candidats (§9.3, point ouvert n° 3 de l'Annexe C).
 * Âge minimum et admission des mineurs sont paramétrables ; un mineur admis
 * doit fournir une autorisation parentale signée.
 */
class AgeRequirement
{
    public const MAJORITY_AGE = 18;

    public function __construct(private readonly Settings $settings) {}

    public function ageOn(DateTimeInterface $birthDate): int
    {
        return (int) now()->diffInYears($birthDate, true);
    }

    public function isMinor(DateTimeInterface $birthDate): bool
    {
        return $this->ageOn($birthDate) < self::MAJORITY_AGE;
    }

    /**
     * Âge en dessous duquel une candidature est refusée.
     */
    public function minimumAge(): int
    {
        $minimum = $this->settings->integer('candidature.minimum_age');

        return $this->minorsAllowed() ? $minimum : max($minimum, self::MAJORITY_AGE);
    }

    public function minorsAllowed(): bool
    {
        return (bool) $this->settings->get('candidature.minors_allowed');
    }

    public function isEligible(DateTimeInterface $birthDate): bool
    {
        return $this->ageOn($birthDate) >= $this->minimumAge();
    }

    public function refusalMessage(): string
    {
        return __('candidature.age_refused', ['age' => $this->minimumAge()]);
    }
}
