<?php

namespace App\Domain\Identity\Enums;

/**
 * Fichiers d'une vérification d'identité. Les faces de la pièce acceptent image ou PDF ;
 * le selfie et la photo de profil, des images seulement.
 */
enum IdentityFileKind: string
{
    case Recto = 'recto';
    case Verso = 'verso';
    case Page = 'page';
    case Selfie = 'selfie';
    /** Ancienne photo de profil : plus demandée (le selfie sert de photo du badge), conservée pour les dossiers existants. */
    case ProfilePhoto = 'profile_photo';

    public function label(): string
    {
        return __("identity.file_kinds.{$this->value}");
    }

    public function isDocumentSide(): bool
    {
        return in_array($this, [self::Recto, self::Verso, self::Page], true);
    }

    /**
     * Types acceptés, contrôlés sur le contenu du fichier (pas sur l'extension).
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        $images = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];

        return $this->isDocumentSide() ? [...$images, 'application/pdf'] : $images;
    }
}
