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
     * @return list<array{label: string, value: string, icon: string, hint: string, tone: string, route: ?string}>
     */
    #[Computed]
    public function stats(): array
    {
        $staffRoles = array_map(fn (Role $role) => $role->value, [Role::Admin, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule, Role::Superviseur, Role::Coordonnateur]);

        if ($this->isCoordination) {
            return [
                ['label' => __('Relais à valider'), 'value' => '0', 'icon' => 'inbox', 'hint' => __('File de validation de votre commune'), 'tone' => 'accent', 'route' => null],
                ['label' => __('Candidatures à examiner'), 'value' => '0', 'icon' => 'users', 'hint' => __('Nouveaux Relais en attente'), 'tone' => 'default', 'route' => null],
                ['label' => __('Relais actifs'), 'value' => (string) User::role(Role::Relais->value)->count(), 'icon' => 'signal', 'hint' => __('Au moins un envoi sur 30 jours'), 'tone' => 'success', 'route' => null],
                ['label' => __('Délai cible urgent'), 'value' => app(Settings::class)->integer('validation.sla_urgent_minutes').' min', 'icon' => 'clock', 'hint' => __('Au-delà, escalade au suppléant'), 'tone' => 'default', 'route' => null],
            ];
        }

        $canAudit = $this->user->hasPermissionTo(Permission::AuditView->value);

        return [
            [
                'label' => __('Communes ouvertes'),
                'value' => Commune::query()->active()->count().' / '.Commune::query()->count(),
                'icon' => 'map-pin',
                'hint' => __('Ouvertes aux inscriptions de Relais'),
                'tone' => 'accent',
                'route' => $this->user->hasPermissionTo(Permission::TerritoireManage->value) ? 'console.communes' : null,
            ],
            [
                'label' => __('Équipe'),
                'value' => (string) User::role($staffRoles)->count(),
                'icon' => 'users',
                'hint' => __('Rédaction, coordination, administration'),
                'tone' => 'default',
                'route' => $this->user->hasPermissionTo(Permission::UsersManage->value) ? 'console.team' : null,
            ],
            [
                'label' => __('Relais et candidats'),
                'value' => (string) User::role([Role::Relais->value, Role::Candidat->value])->count(),
                'icon' => 'signal',
                'hint' => __('Comptes créés depuis l’application'),
                'tone' => 'success',
                'route' => null,
            ],
            [
                'label' => __('Événements · 24 h'),
                'value' => $canAudit ? (string) AuditLog::query()->where('occurred_at', '>=', now()->subDay())->count() : '—',
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
