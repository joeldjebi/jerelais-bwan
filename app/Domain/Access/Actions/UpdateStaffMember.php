<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\CommuneAssignment;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Modifie un membre de l'équipe (ADM-21, ADM-24) : nom, adresse, rôle, communes coordonnées ou régions supervisées.
 * Un changement de rôle ou de périmètre ferme ses sessions (ses droits changent). Chaque modification est auditée.
 */
class UpdateStaffMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $communeIds
     * @param  list<int>  $regionIds
     */
    public function handle(User $member, string $name, string $email, Role $role, User $actor, array $communeIds = [], array $regionIds = [], CommuneAssignment $assignment = CommuneAssignment::Titulaire): void
    {
        if (in_array($role, [Role::Relais, Role::Candidat], true)) {
            throw new InvalidArgumentException(__('Ce rôle ne correspond pas à un membre de l’équipe.'));
        }

        if ($member->is($actor) && $member->hasRole(Role::Admin->value) && $role !== Role::Admin) {
            throw new InvalidArgumentException(__('Vous ne pouvez pas retirer votre propre rôle d’administrateur.'));
        }

        DB::transaction(function () use ($member, $name, $email, $role, $actor, $communeIds, $regionIds, $assignment): void {
            $before = [
                'role' => $member->getRoleNames()->first(),
                'communes' => $member->communes()->pluck('communes.id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
                'regions' => $member->regions()->pluck('regions.id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            ];

            $member->forceFill(['name' => $name, 'email' => Str::lower($email)]);
            $identityChanged = $member->isDirty(['name', 'email']);
            $member->save();

            $member->syncRoles([$role->value]);
            $member->communes()->syncWithPivotValues($role === Role::Coordonnateur ? $communeIds : [], ['assignment' => $assignment->value]);
            $member->regions()->sync($role === Role::Superviseur ? $regionIds : []);

            $after = [
                'role' => $role->value,
                'communes' => collect($role === Role::Coordonnateur ? $communeIds : [])->sort()->values()->all(),
                'regions' => collect($role === Role::Superviseur ? $regionIds : [])->sort()->values()->all(),
            ];

            if (! $identityChanged && $before === $after) {
                return;
            }

            if ($before !== $after && ! $member->is($actor)) {
                $member->tokens()->delete();
                DB::table('sessions')->where('user_id', $member->id)->delete();
            }

            $this->audit->record('users.updated', $member, ['name' => $member->name, 'before' => $before, 'after' => $after], $actor);
        });
    }
}
