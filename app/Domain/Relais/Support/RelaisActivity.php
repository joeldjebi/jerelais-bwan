<?php

namespace App\Domain\Relais\Support;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Models\RelaisStatusChange;
use App\Domain\Settings\Settings;
use App\Models\User;

/**
 * Tableau de bord personnel du Relais (MOB-24) : volumes, taux de validation, niveau et badges.
 * Un relais compte comme validé dès qu'il a été validé une fois, même s'il a ensuite été sélectionné, diffusé ou archivé.
 */
class RelaisActivity
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{sent: int, validated: int, broadcast: int, validation_rate: int|null, level: array{key: string, label: string, next: array{key: string, label: string, remaining: int}|null}, badges: list<array{key: string, label: string, earned: bool}>}
     */
    public function for(User $user): array
    {
        $items = RelaisItem::withoutCommuneRestriction()->where('user_id', $user->id);

        $sent = (clone $items)->whereNotNull('submitted_at')->count();
        $validated = (clone $items)->whereIn('id', RelaisStatusChange::query()->select('relais_item_id')->where('to', 'validated'))->count();
        $broadcast = (clone $items)->whereNotNull('broadcast_at')->count();
        $decided = (clone $items)->whereIn('id', RelaisStatusChange::query()->select('relais_item_id')->whereIn('to', ['validated', 'rejected']))->count();

        return [
            'sent' => $sent,
            'validated' => $validated,
            'broadcast' => $broadcast,
            'validation_rate' => $decided === 0 ? null : (int) round($validated * 100 / $decided),
            'level' => $this->level($validated),
            'badges' => [
                ['key' => 'first_relais', 'label' => __('relais_profils.badges.first_relais'), 'earned' => $sent > 0],
                ['key' => 'first_validated', 'label' => __('relais_profils.badges.first_validated'), 'earned' => $validated > 0],
                ['key' => 'first_broadcast', 'label' => __('relais_profils.badges.first_broadcast'), 'earned' => $broadcast > 0],
                ['key' => 'showcased', 'label' => __('relais_profils.badges.showcased'), 'earned' => (clone $items)->whereNotNull('showcased_at')->exists()],
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, next: array{key: string, label: string, remaining: int}|null}
     */
    private function level(int $validated): array
    {
        $thresholds = [
            'debutant' => 0,
            'bronze' => 1,
            'argent' => $this->settings->integer('levels.silver_validated'),
            'or' => $this->settings->integer('levels.gold_validated'),
        ];

        $current = 'debutant';
        $next = null;

        foreach ($thresholds as $key => $threshold) {
            if ($validated >= $threshold) {
                $current = $key;
            } elseif ($next === null) {
                $next = ['key' => $key, 'label' => __("relais_profils.levels.{$key}"), 'remaining' => $threshold - $validated];
            }
        }

        return ['key' => $current, 'label' => __("relais_profils.levels.{$current}"), 'next' => $next];
    }
}
