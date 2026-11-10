<?php

namespace App\Domain\Identity\Enums;

/**
 * Pièces d'identité acceptées (MOB-01) et faces à fournir pour chacune.
 */
enum IdentityDocumentType: string
{
    case Cni = 'cni';
    case Passport = 'passeport';
    case DrivingLicence = 'permis';
    case ConsularCard = 'carte_consulaire';
    case IdentityCertificate = 'attestation_identite';

    public function label(): string
    {
        return __("identity.document_types.{$this->value}");
    }

    /**
     * Faces de la pièce à envoyer : recto et verso, ou une seule page.
     *
     * @return list<IdentityFileKind>
     */
    public function sides(): array
    {
        return match ($this) {
            self::Passport, self::IdentityCertificate => [IdentityFileKind::Page],
            default => [IdentityFileKind::Recto, IdentityFileKind::Verso],
        };
    }

    /**
     * Tous les fichiers exigés : faces de la pièce, selfie et photo de profil.
     *
     * @return list<IdentityFileKind>
     */
    public function requiredFiles(): array
    {
        return [...$this->sides(), IdentityFileKind::Selfie, IdentityFileKind::ProfilePhoto];
    }
}
