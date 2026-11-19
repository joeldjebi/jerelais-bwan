<?php

namespace App\Domain\Notification\Enums;

enum AnnouncementStatus: string
{
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("announcements.statuses.{$this->value}");
    }
}
