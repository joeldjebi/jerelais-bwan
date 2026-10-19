<?php

namespace App\Domain\Candidature\Enums;

/**
 * Étapes du formulaire de candidature (MOB-02), enregistrées séparément (sauvegarde automatique).
 */
enum CandidatureStep: string
{
    case Identite = 'identite';
    case Territoire = 'territoire';
    case Profil = 'profil';

    public function label(): string
    {
        return __("candidature.steps.{$this->value}");
    }

    /**
     * Champs du dossier renseignés par cette étape.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Identite => ['last_name', 'first_names', 'birth_date', 'gender', 'email'],
            self::Territoire => ['commune', 'localite', 'lieu_type'],
            self::Profil => ['occupation', 'languages', 'interests', 'motivation'],
        };
    }

    /**
     * Champs obligatoires pour que l'étape soit considérée comme complète.
     *
     * @return list<string>
     */
    public function requiredColumns(): array
    {
        return match ($this) {
            self::Identite => ['last_name', 'first_names', 'birth_date', 'gender'],
            self::Territoire => ['commune_id'],
            self::Profil => ['occupation', 'languages', 'motivation'],
        };
    }
}
