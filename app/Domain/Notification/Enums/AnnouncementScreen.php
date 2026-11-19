<?php

namespace App\Domain\Notification\Enums;

/**
 * Écran que l'application ouvre quand le Relais touche la notification (`data.screen`).
 */
enum AnnouncementScreen: string
{
    case Home = 'home';
    case Announcements = 'announcements';
    case Appels = 'appels';
    case Relais = 'relais';
    case Gains = 'gains';
    case Identity = 'identity';
    case Profile = 'profile';

    public function label(): string
    {
        return __("announcements.screens.{$this->value}");
    }
}
