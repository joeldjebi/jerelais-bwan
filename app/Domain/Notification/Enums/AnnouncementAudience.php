<?php

namespace App\Domain\Notification\Enums;

enum AnnouncementAudience: string
{
    case All = 'all';
    case Regions = 'regions';
    case Communes = 'communes';
    case Relais = 'relais';
    case IdentityPending = 'identity_pending';
    case PayoutMissing = 'payout_missing';

    public function label(): string
    {
        return __("announcements.audiences.{$this->value}");
    }
}
