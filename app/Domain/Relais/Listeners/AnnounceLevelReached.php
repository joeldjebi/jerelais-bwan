<?php

namespace App\Domain\Relais\Listeners;

use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Models\RelaisStatusChange;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Settings\Settings;

/**
 * Nouveau niveau du Relais (MOB-24) : Bronze au premier relais validé, puis Argent et Or selon les Paramètres.
 * Notification push seulement, au moment exact où le seuil est atteint (une revalidation ne compte pas deux fois).
 */
class AnnounceLevelReached
{
    public function __construct(
        private readonly Settings $settings,
        private readonly NotifyUser $notify,
    ) {}

    public function handle(RelaisStatusChanged $event): void
    {
        $relais = $event->relais;

        if ($event->to !== 'validated' || RelaisStatusChange::query()->where('relais_item_id', $relais->id)->where('to', 'validated')->count() > 1) {
            return;
        }

        $validated = RelaisItem::withoutCommuneRestriction()->where('user_id', $relais->user_id)
            ->whereIn('id', RelaisStatusChange::query()->select('relais_item_id')->where('to', 'validated'))
            ->count();

        $level = match ($validated) {
            1 => 'bronze',
            $this->settings->integer('levels.silver_validated') => 'argent',
            $this->settings->integer('levels.gold_validated') => 'or',
            default => null,
        };

        if ($level === null) {
            return;
        }

        $label = __("relais_profils.levels.{$level}");

        $this->notify->handle($relais->loadMissing('user')->user, new PushMessage(
            __('notifications.level.title', ['level' => $label]),
            __('notifications.level.body', ['level' => $label, 'count' => $validated]),
            ['type' => 'account.level', 'level' => $level],
        ), null, 'level');
    }
}
