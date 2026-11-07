<?php

namespace App\Livewire\Console;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Relais\Support\CommuneStats;
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
 * @property-read array{active_relais: int, to_validate: int, candidatures: int, received: int, validated: int, rejected: int, broadcast: int, average_minutes: int|null} $communeStats
 * @property-read list<array{name: string, relais: int, items: int}> $coverage
 * @property-read Collection<int, RelaisItem> $queue
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

        $rings = [
            ['label' => __('Communes ouvertes'), 'value' => $open.'/'.$total, 'ratio' => $open / $total, 'color' => '#30d158'],
            ['label' => __('Communes coordonnées'), 'value' => $covered.'/'.$total, 'ratio' => $covered / $total, 'color' => '#0a84ff'],
        ];

        // L'anneau de mise en service ne concerne que l'administration.
        if ($setup !== []) {
            array_unshift($rings, ['label' => __('Mise en service'), 'value' => $done.'/'.count($setup), 'ratio' => $done / count($setup), 'color' => '#ff9f0a']);
        }

        return $rings;
    }

    /**
     * @return array{active_relais: int, to_validate: int, candidatures: int, received: int, validated: int, rejected: int, broadcast: int, average_minutes: int|null}
     */
    #[Computed]
    public function communeStats(): array
    {
        return app(CommuneStats::class)->summary();
    }

    /**
     * @return list<array{name: string, relais: int, items: int}>
     */
    #[Computed]
    public function coverage(): array
    {
        return app(CommuneStats::class)->coverage();
    }

    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function queue(): Collection
    {
        return RelaisItem::query()
            ->whereIn('state', ['received', 'in_review'])
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->orderByRaw("case urgency when 'urgent' then 0 when 'important' then 1 else 2 end")
            ->orderBy('received_at')
            ->limit(5)
            ->get();
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
                ['label' => __('Relais à valider'), 'count' => $this->communeStats['to_validate'], 'suffix' => '', 'value' => '', 'icon' => 'inbox', 'hint' => __('File de validation de votre commune'), 'tone' => 'accent', 'route' => 'console.validation'],
                ['label' => __('Candidatures'), 'count' => $this->communeStats['candidatures'], 'suffix' => '', 'value' => '', 'icon' => 'users', 'hint' => __('Nouveaux Relais à examiner'), 'tone' => 'default', 'route' => 'console.candidatures'],
                ['label' => __('Relais actifs'), 'count' => $this->communeStats['active_relais'], 'suffix' => '', 'value' => '', 'icon' => 'signal', 'hint' => __('Recrutés et non suspendus'), 'tone' => 'success', 'route' => 'console.relais'],
                ['label' => __('Délai moyen'), 'count' => $this->communeStats['average_minutes'], 'suffix' => ' min', 'value' => $this->communeStats['average_minutes'] === null ? '—' : '', 'icon' => 'clock', 'hint' => __('Avant décision, sur 30 jours'), 'tone' => 'default', 'route' => null],
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
