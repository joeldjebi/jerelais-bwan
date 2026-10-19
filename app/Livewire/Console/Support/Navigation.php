<?php

namespace App\Livewire\Console\Support;

use App\Domain\Access\Enums\Permission;
use App\Models\User;

/**
 * Navigation de la console : chaque entrée n'apparaît que si l'utilisateur détient la permission requise.
 * Sert à la barre latérale et à la palette de commandes (⌘K).
 */
class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, tint: string, keywords: string}>}>
     */
    public static function for(User $user): array
    {
        $groups = [
            [
                'label' => __('Pilotage'),
                'items' => [
                    ['label' => __('Accueil'), 'route' => 'console.home', 'icon' => 'home', 'tint' => 'orange', 'keywords' => 'tableau de bord accueil', 'permission' => Permission::ConsoleAccess],
                ],
            ],
            [
                'label' => __('Réseau'),
                'items' => [
                    ['label' => __('Candidatures'), 'route' => 'console.candidatures', 'icon' => 'inbox', 'tint' => 'pink', 'keywords' => 'recrutement dossiers candidats relais décision', 'permission' => Permission::CandidaturesValidate],
                    ['label' => __('Communes'), 'route' => 'console.communes', 'icon' => 'map-pin', 'tint' => 'blue', 'keywords' => 'territoire référentiel régions pilote', 'permission' => Permission::TerritoireManage],
                    ['label' => __('Équipe'), 'route' => 'console.team', 'icon' => 'users', 'tint' => 'green', 'keywords' => 'coordonnateurs journalistes comptes membres', 'permission' => Permission::UsersManage],
                ],
            ],
            [
                'label' => __('Administration'),
                'items' => [
                    ['label' => __('Paramètres'), 'route' => 'console.settings', 'icon' => 'layers', 'tint' => 'gray', 'keywords' => 'réglages délais médias conservation', 'permission' => Permission::SettingsManage],
                    ['label' => __('Textes juridiques'), 'route' => 'console.legal', 'icon' => 'book', 'tint' => 'teal', 'keywords' => 'charte conditions confidentialité cgu versions', 'permission' => Permission::SettingsManage],
                    ['label' => __('Journal d’audit'), 'route' => 'console.audit', 'icon' => 'shield', 'tint' => 'purple', 'keywords' => 'traçabilité historique sécurité', 'permission' => Permission::AuditView],
                ],
            ],
        ];

        $visible = [];

        foreach ($groups as $group) {
            $items = array_values(array_map(
                fn (array $item): array => ['label' => $item['label'], 'route' => $item['route'], 'icon' => $item['icon'], 'tint' => $item['tint'], 'keywords' => $item['keywords']],
                array_filter($group['items'], fn (array $item): bool => $user->hasPermissionTo($item['permission']->value)),
            ));

            if ($items !== []) {
                $visible[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * Commandes de la palette (⌘K) : pages accessibles + actions de compte.
     *
     * @return list<array{label: string, group: string, url: string, icon: string, tint: string, keywords: string}>
     */
    public static function commands(User $user): array
    {
        $commands = [];

        foreach (self::for($user) as $group) {
            foreach ($group['items'] as $item) {
                $commands[] = ['label' => $item['label'], 'group' => __('Aller à'), 'url' => route($item['route']), 'icon' => $item['icon'], 'tint' => $item['tint'], 'keywords' => $item['keywords']];
            }
        }

        $commands[] = ['label' => __('Mon compte'), 'group' => __('Compte'), 'url' => route('console.account'), 'icon' => 'users', 'tint' => 'teal', 'keywords' => 'profil mot de passe nom'];
        $commands[] = ['label' => __('Sécurité et double authentification'), 'group' => __('Compte'), 'url' => route('console.security'), 'icon' => 'shield', 'tint' => 'pink', 'keywords' => '2fa codes de secours'];

        return $commands;
    }
}
