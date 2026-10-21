<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\CommuneAssignment;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Crée un membre de l'équipe (ADM-21, ADM-24) : aucun mot de passe n'est choisi par l'administrateur,
 * le membre reçoit un lien pour définir le sien, puis activera sa double authentification.
 */
class CreateStaffMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $communeIds  communes coordonnées (coordonnateur)
     * @param  list<int>  $regionIds  régions supervisées (superviseur)
     */
    public function handle(string $name, string $email, Role $role, User $actor, array $communeIds = [], array $regionIds = [], CommuneAssignment $assignment = CommuneAssignment::Titulaire): User
    {
        if (in_array($role, [Role::Relais, Role::Candidat], true)) {
            throw new InvalidArgumentException('Ce rôle ne correspond pas à un membre de l’équipe.');
        }

        $user = DB::transaction(function () use ($name, $email, $role, $actor, $communeIds, $regionIds, $assignment): User {
            $user = new User(['name' => $name, 'email' => Str::lower($email)]);
            $user->save();
            $user->assignRole($role->value);

            if ($role === Role::Coordonnateur) {
                $user->communes()->syncWithPivotValues($communeIds, ['assignment' => $assignment->value]);
            }

            if ($role === Role::Superviseur) {
                $user->regions()->sync($regionIds);
            }

            $this->audit->record('users.created', $user, [
                'role' => $role->value,
                'communes' => $communeIds,
                'regions' => $regionIds,
                'assignment' => $role === Role::Coordonnateur ? $assignment->value : null,
            ], $actor);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }
}
