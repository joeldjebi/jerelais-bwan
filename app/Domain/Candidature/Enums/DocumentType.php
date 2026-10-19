<?php

namespace App\Domain\Candidature\Enums;

use App\Domain\Candidature\Models\Candidature;

/**
 * Pièces demandées au candidat (MOB-02, MOB-03, §9.3).
 */
enum DocumentType: string
{
    case ProfilePhoto = 'profile_photo';
    case IdFront = 'id_front';
    case IdBack = 'id_back';
    case Selfie = 'selfie';
    case ParentalAuthorization = 'parental_authorization';

    public function label(): string
    {
        return __("candidature.documents.{$this->value}.label");
    }

    public function hint(): string
    {
        return __("candidature.documents.{$this->value}.hint");
    }

    /**
     * Types acceptés, contrôlés sur le contenu du fichier (pas sur l'extension).
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        $images = ['image/jpeg', 'image/png', 'image/heic', 'image/heif'];

        return $this === self::ParentalAuthorization ? [...$images, 'application/pdf'] : $images;
    }

    /**
     * Pièce attendue pour ce dossier : l'autorisation parentale ne concerne que les mineurs.
     */
    public function appliesTo(Candidature $candidature): bool
    {
        return $this !== self::ParentalAuthorization || $candidature->is_minor;
    }

    /**
     * @return list<self>
     */
    public static function requiredFor(Candidature $candidature): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->appliesTo($candidature)));
    }
}
