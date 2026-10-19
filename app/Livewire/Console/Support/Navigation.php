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
                    ['label' => __('Flux des relais'), 'route' => 'console.flux', 'icon' => 'signal', 'tint' => 'brand', 'keywords' => 'flux temps réel recherche filtres', 'permission' => Permission::RelaisViewPending],
                    ['label' => __('Carte'), 'route' => 'console.map', 'icon' => 'map-pin', 'tint' => 'teal', 'keywords' => 'carte géographie communes quartiers', 'permission' => Permission::RelaisViewPending],
                    ['label' => __('Rapports'), 'route' => 'console.reports', 'icon' => 'chart', 'tint' => 'green', 'keywords' => 'rapports indicateurs excel pdf statistiques', 'permission' => Permission::ReportsView],
                    ['label' => __('Validation des relais'), 'route' => 'console.validation', 'icon' => 'bolt', 'tint' => 'blue', 'keywords' => 'relais file vérification urgents médias', 'permission' => Permission::RelaisViewPending],
                ],
            ],
            [
                'label' => __('Rédaction'),
                'items' => [
                    ['label' => __('Exploitation'), 'route' => 'console.editorial', 'icon' => 'sparkles', 'tint' => 'purple', 'keywords' => 'rédaction sélection arbitrage diffusé émission', 'permission' => Permission::RelaisArbitrate],
                    ['label' => __('Appels à relais'), 'route' => 'console.appels', 'icon' => 'bell', 'tint' => 'orange', 'keywords' => 'appels zone sujet consignes', 'permission' => [Permission::AppelsCreate, Permission::AppelsCreateLocal]],
                    ['label' => __('Collections'), 'route' => 'console.collections', 'icon' => 'layers', 'tint' => 'teal', 'keywords' => 'collections dossiers sujets événements', 'permission' => Permission::RelaisArbitrate],
                ],
            ],
            [
                'label' => __('Réseau'),
                'items' => [
                    ['label' => __('Relais'), 'route' => 'console.relais', 'icon' => 'signal', 'tint' => 'brand', 'keywords' => 'relais observateurs suspension radiation fiche', 'permission' => [Permission::RelaisProfilesSuspend, Permission::RelaisValidate]],
                    ['label' => __('Messagerie'), 'route' => 'console.messagerie', 'icon' => 'inbox', 'tint' => 'blue', 'keywords' => 'messages relais conversation', 'permission' => Permission::RelaisValidate],
                    ['label' => __('Candidatures'), 'route' => 'console.candidatures', 'icon' => 'inbox', 'tint' => 'pink', 'keywords' => 'recrutement dossiers candidats relais décision', 'permission' => Permission::CandidaturesValidate],
                    ['label' => __('Communes'), 'route' => 'console.communes', 'icon' => 'map-pin', 'tint' => 'blue', 'keywords' => 'territoire référentiel régions pilote', 'permission' => Permission::TerritoireManage],
                    ['label' => __('Équipe'), 'route' => 'console.team', 'icon' => 'users', 'tint' => 'green', 'keywords' => 'coordonnateurs journalistes comptes membres', 'permission' => Permission::UsersManage],
                ],
            ],
            [
                'label' => __('Administration'),
                'items' => [
                    ['label' => __('Paramètres'), 'route' => 'console.settings', 'icon' => 'layers', 'tint' => 'gray', 'keywords' => 'réglages délais médias conservation', 'permission' => Permission::SettingsManage],
                    ['label' => __('Données personnelles'), 'route' => 'console.privacy', 'icon' => 'eye-off', 'tint' => 'pink', 'keywords' => 'rgpd artci suppression export accès', 'permission' => Permission::UsersManage],
                    ['label' => __('Textes juridiques'), 'route' => 'console.legal', 'icon' => 'book', 'tint' => 'teal', 'keywords' => 'charte conditions confidentialité cgu versions', 'permission' => Permission::SettingsManage],
                    ['label' => __('Journal d’audit'), 'route' => 'console.audit', 'icon' => 'shield', 'tint' => 'purple', 'keywords' => 'traçabilité historique sécurité', 'permission' => Permission::AuditView],
                ],
            ],
        ];

        $visible = [];

        foreach ($groups as $group) {
            $items = array_values(array_map(
                fn (array $item): array => ['label' => $item['label'], 'route' => $item['route'], 'icon' => $item['icon'], 'tint' => $item['tint'], 'keywords' => $item['keywords']],
                array_filter($group['items'], fn (array $item): bool => collect(is_array($item['permission']) ? $item['permission'] : [$item['permission']])
                    ->contains(fn (Permission $permission): bool => $user->hasPermissionTo($permission->value))),
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
