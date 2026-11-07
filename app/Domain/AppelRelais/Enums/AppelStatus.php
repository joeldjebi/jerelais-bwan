<?php

namespace App\Domain\AppelRelais\Enums;

enum AppelStatus: string
{
    case PendingApproval = 'pending_approval';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __("appels_relais.statuses.{$this->value}");
    }
}
