<?php

namespace App\Domain\Access\Enums;

/**
 * Rôles de la plateforme (§2.1, ADM-24).
 */
enum Role: string
{
    case Admin = 'admin';
    case RedacteurEnChef = 'redacteur_en_chef';
    case Journaliste = 'journaliste';
    case LectureSeule = 'lecture_seule';
    case Superviseur = 'superviseur';
    case Coordonnateur = 'coordonnateur';
    case Relais = 'relais';
    case Candidat = 'candidat';

    public function label(): string
    {
        return __("access.roles.{$this->value}");
    }

    /**
     * Rôles dont l'accès aux données communales est limité à leur périmètre territorial.
     *
     * @return list<self>
     */
    public static function territorial(): array
    {
        return [self::Coordonnateur, self::Superviseur];
    }

    /**
     * Permissions accordées à chaque rôle : traduction de la matrice de l'Annexe A.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // L'administrateur voit et paramètre tout, mais n'envoie pas de relais (Annexe A).
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission) => ! in_array($permission, [Permission::RelaisSubmit, Permission::RelaisViewOwn, Permission::CandidatureApply], true),
            )),
            self::RedacteurEnChef, self::Journaliste => [
                Permission::ConsoleAccess, Permission::RelaisViewPending, Permission::RelaisArbitrate,
                Permission::MediasDownloadOriginal, Permission::AppelsCreate, Permission::ReportsView,
            ],
            self::LectureSeule => [Permission::ConsoleAccess, Permission::RelaisViewPending, Permission::ReportsView],
            self::Superviseur => [
                Permission::ConsoleAccess, Permission::RelaisViewPending, Permission::RelaisValidate,
                Permission::CandidaturesValidate,
            ],
            self::Coordonnateur => [
                Permission::ConsoleAccess, Permission::RelaisSubmit, Permission::RelaisViewOwn,
                Permission::RelaisViewPending, Permission::RelaisValidate, Permission::CandidaturesValidate,
                Permission::IdentityView, Permission::AppelsCreateLocal, Permission::RelaisProfilesSuspend,
            ],
            self::Relais => [Permission::RelaisSubmit, Permission::RelaisViewOwn],
            self::Candidat => [Permission::CandidatureApply],
        };
    }
}
