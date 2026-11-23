<?php

namespace App\Domain\Editorial\Enums;

/**
 * Visuel public d'un relais sur la vitrine « Vu sur REFLET TV » (WEB-05, ADM-17).
 */
enum ShowcaseVisual: string
{
    /** Titre et texte seulement. */
    case None = 'none';

    /** Vignette de l'image d'origine : interdit si un floutage est demandé ou en présence de mineurs. */
    case Original = 'original';

    /** Vignette floutée en entier par le serveur : aucun visage reconnaissable. */
    case Blurred = 'blurred';

    /** Version floutée par la rédaction (photo ou vidéo), déposée dans la console. */
    case Edited = 'edited';

    public function label(): string
    {
        return __("editorial.visuals.{$this->value}");
    }

    public function hasImage(): bool
    {
        return $this !== self::None;
    }
}
