<?php

namespace App\Domain\Notification\Enums;

enum PushResult: string
{
    /** Message accepté par le service de notifications. */
    case Sent = 'sent';

    /** Jeton expiré, désinstallé ou d'un autre projet : il est oublié. */
    case InvalidToken = 'invalid_token';

    /** Erreur du service, du réseau ou de configuration. */
    case Failed = 'failed';
}
