<?php

namespace App\Domain\Candidature\Enums;

/**
 * Profession ou statut du candidat (MOB-02).
 */
enum Occupation: string
{
    case Eleve = 'eleve';
    case Etudiant = 'etudiant';
    case Commercant = 'commercant';
    case Salarie = 'salarie';
    case Independant = 'independant';
    case Artisan = 'artisan';
    case Agriculteur = 'agriculteur';
    case SansEmploi = 'sans_emploi';
    case Autre = 'autre';

    public function label(): string
    {
        return __("candidature.occupations.{$this->value}");
    }
}
