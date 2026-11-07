<?php

namespace App\Domain\Media\Enums;

/**
 * Avancement d'un média : envoi, contrôle, mise à disposition.
 */
enum MediaStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Ready = 'ready';
    case Rejected = 'rejected';

    /**
     * L'envoi du fichier est terminé (le contrôle peut encore être en cours).
     */
    public function isReceived(): bool
    {
        return in_array($this, [self::Uploaded, self::Processing, self::Ready], true);
    }
}
