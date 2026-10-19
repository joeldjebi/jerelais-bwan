<?php

namespace App\Livewire\Console;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Accueil de la console, adapté au rôle (administration, rédaction ou coordination).
 *
 * @property-read User $user
 * @property-read bool $isCoordination
 */
#[Title('Accueil')]
class Dashboard extends Component
{
    #[Computed]
    public function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->loadMissing('communes.region');
    }

    #[Computed]
    public function isCoordination(): bool
    {
        return $this->user->hasAnyRole([Role::Coordonnateur->value, Role::Superviseur->value]);
    }

    /**
     * Étapes de mise en service de la plateforme, calculées sur l'état réel (administrateur).
     *
     * @return list<array{label: string, hint: string, done: bool, route: ?string}>
     */
    #[Computed]
    public function setup(): array
    {
        if (! $this->user->hasPermissionTo(Permission::SettingsManage->value)) {
            return [];
        }

        return [
            [
                'label' => __('Activer votre double authentification'),
                'hint' => __('Obligatoire pour toute l’équipe.'),
                'done' => $this->user->hasConfirmedTwoFactor(),
                'route' => 'console.security',
            ],
            [
                'label' => __('Importer les contours des communes'),
                'hint' => __('Indispensable pour router chaque relais vers la bonne commune.'),
                'done' => Commune::query()->whereNotNull('geom')->exists(),
                'route' => 'console.communes',
            ],
            [
                'label' => __('Ouvrir les communes du pilote'),
                'hint' => __('Les Relais ne peuvent s’inscrire que dans une commune ouverte.'),
                'done' => Commune::query()->active()->exists(),
                'route' => 'console.communes',
            ],
            [
                'label' => __('Désigner les coordonnateurs'),
                'hint' => __('Au moins un titulaire par commune ouverte.'),
                'done' => User::role(Role::Coordonnateur->value)->whereHas('communes')->exists(),
                'route' => 'console.team',
            ],
            [
                'label' => __('Brancher la passerelle SMS'),
                'hint' => __('Les codes de connexion sont aujourd’hui écrits dans le journal local.'),
                'done' => config('jerelais.sms.driver') !== 'log',
                'route' => null,
            ],
        ];
    }

    /**
     * Anneaux d'activité (façon app Forme) : progression de la mise en service et couverture du réseau.
     *
     * @return list<array{label: string, value: string, ratio: float, color: string}>
     */
    #[Computed]
    public function rings(): array
    {
        $setup = $this->setup();
        $done = count(array_filter($setup, fn (array $step): bool => $step['done']));
        $total = max(1, Commune::query()->count());
        $open = Commune::query()->active()->count();
        $covered = Commune::query()->whereHas('coordinators')->count();

        return [
            ['label' => __('Mise en service'), 'value' => $done.'/'.max(1, count($setup)), 'ratio' => $setup === [] ? 1.0 : $done / count($setup), 'color' => '#ff9f0a'],
            ['label' => __('Communes ouvertes'), 'value' => $open.'/'.$total, 'ratio' => $open / $total, 'color' => '#30d158'],
            ['label' => __('Communes coordonnées'), 'value' => $covered.'/'.$total, 'ratio' => $covered / $total, 'color' => '#0a84ff'],
        ];
    }

    /**
     * Indicateurs de l'accueil ; « count » est animé à l'affichage, « suffix » le complète.
     *
     * @return list<array{label: string, count: ?int, suffix: string, value: string, icon: string, hint: string, tone: string, route: ?string}>
     */
    #[Computed]
    public function stats(): array
    {
        $staffRoles = array_map(fn (Role $role) => $role->value, [Role::Admin, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule, Role::Superviseur, Role::Coordonnateur]);

        if ($this->isCoordination) {
            return [
                ['label' => __('Relais à valider'), 'count' => 0, 'suffix' => '', 'value' => '', 'icon' => 'inbox', 'hint' => __('File de validation de votre commune'), 'tone' => 'accent', 'route' => null],
                ['label' => __('Candidatures'), 'count' => 0, 'suffix' => '', 'value' => '', 'icon' => 'users', 'hint' => __('Nouveaux Relais à examiner'), 'tone' => 'default', 'route' => null],
                ['label' => __('Relais actifs'), 'count' => User::role(Role::Relais->value)->count(), 'suffix' => '', 'value' => '', 'icon' => 'signal', 'hint' => __('Au moins un envoi sur 30 jours'), 'tone' => 'success', 'route' => null],
                ['label' => __('Délai urgent'), 'count' => app(Settings::class)->integer('validation.sla_urgent_minutes'), 'suffix' => ' min', 'value' => '', 'icon' => 'clock', 'hint' => __('Au-delà, escalade au suppléant'), 'tone' => 'default', 'route' => null],
            ];
        }

        $canAudit = $this->user->hasPermissionTo(Permission::AuditView->value);

        return [
            [
                'label' => __('Communes ouvertes'),
                'count' => Commune::query()->active()->count(),
                'suffix' => ' / '.Commune::query()->count(),
                'value' => '',
                'icon' => 'map-pin',
                'hint' => __('Ouvertes aux inscriptions de Relais'),
                'tone' => 'accent',
                'route' => $this->user->hasPermissionTo(Permission::TerritoireManage->value) ? 'console.communes' : null,
            ],
            [
                'label' => __('Équipe'),
                'count' => User::role($staffRoles)->count(),
                'suffix' => '',
                'value' => '',
                'icon' => 'users',
                'hint' => __('Rédaction, coordination, administration'),
                'tone' => 'default',
                'route' => $this->user->hasPermissionTo(Permission::UsersManage->value) ? 'console.team' : null,
            ],
            [
                'label' => __('Relais et candidats'),
                'count' => User::role([Role::Relais->value, Role::Candidat->value])->count(),
                'suffix' => '',
                'value' => '',
                'icon' => 'signal',
                'hint' => __('Comptes créés depuis l’application'),
                'tone' => 'success',
                'route' => null,
            ],
            [
                'label' => __('Événements · 24 h'),
                'count' => $canAudit ? AuditLog::query()->where('occurred_at', '>=', now()->subDay())->count() : null,
                'suffix' => '',
                'value' => '—',
                'icon' => 'shield',
                'hint' => __('Actions tracées dans le journal'),
                'tone' => 'default',
                'route' => $canAudit ? 'console.audit' : null,
            ],
        ];
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function activity(): Collection
    {
        if (! $this->user->hasPermissionTo(Permission::AuditView->value)) {
            return new Collection;
        }

        return AuditLog::query()->with('actor')->orderByDesc('id')->limit(8)->get();
    }

    public function render(): View
    {
        return view('livewire.console.dashboard');
    }
}
