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
 * Crée un membre de l'équipe (ADM-21, ADM-24). Deux façons de lui donner accès :
 * un lien par e-mail pour définir son mot de passe, ou un mot de passe saisi par l'administrateur
 * (utile tant que l'envoi d'e-mails n'est pas configuré). Dans les deux cas, la double authentification
 * est activée à la première connexion.
 */
class CreateStaffMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $communeIds  communes coordonnées (coordonnateur)
     * @param  list<int>  $regionIds  régions supervisées (superviseur)
     * @param  string|null  $password  mot de passe choisi par l'administrateur ; null : lien envoyé par e-mail
     */
    public function handle(string $name, string $email, Role $role, User $actor, array $communeIds = [], array $regionIds = [], CommuneAssignment $assignment = CommuneAssignment::Titulaire, ?string $password = null): User
    {
        if (in_array($role, [Role::Relais, Role::Candidat], true)) {
            throw new InvalidArgumentException('Ce rôle ne correspond pas à un membre de l’équipe.');
        }

        $user = DB::transaction(function () use ($name, $email, $role, $actor, $communeIds, $regionIds, $assignment, $password): User {
            $user = new User(['name' => $name, 'email' => Str::lower($email)]);

            if ($password !== null) {
                $user->forceFill(['password' => $password, 'email_verified_at' => now()]);
            }

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
                'access' => $password !== null ? 'password_set_by_admin' : 'email_link',
            ], $actor);

            return $user;
        });

        if ($password === null) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return $user;
    }
}
