<?php

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Models\User;

/**
 * Matrice des droits de l'Annexe A, case par case.
 * Le « sa commune » est vérifié séparément (CommuneScope).
 */
dataset('matrice', [
    'déposer une candidature' => [Permission::CandidatureApply, [Role::Candidat]],
    'envoyer un relais' => [Permission::RelaisSubmit, [Role::Relais, Role::Coordonnateur]],
    'voir ses propres relais' => [Permission::RelaisViewOwn, [Role::Relais, Role::Coordonnateur]],
    'voir les relais en attente' => [Permission::RelaisViewPending, [Role::Coordonnateur, Role::Superviseur, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule, Role::Admin]],
    'valider un relais' => [Permission::RelaisValidate, [Role::Coordonnateur, Role::Superviseur, Role::Admin]],
    'arbitrer un relais' => [Permission::RelaisArbitrate, [Role::RedacteurEnChef, Role::Journaliste, Role::Admin]],
    'valider une candidature' => [Permission::CandidaturesValidate, [Role::Coordonnateur, Role::Superviseur, Role::Admin]],
    'voir une pièce d’identité' => [Permission::IdentityView, [Role::Coordonnateur, Role::Admin]],
    'télécharger en qualité d’origine' => [Permission::MediasDownloadOriginal, [Role::RedacteurEnChef, Role::Journaliste, Role::Admin]],
    'exporter un relais non validé' => [Permission::RelaisExportUnvalidated, [Role::Admin]],
    'lancer un appel à relais' => [Permission::AppelsCreate, [Role::RedacteurEnChef, Role::Journaliste, Role::Admin]],
    'lancer un appel local' => [Permission::AppelsCreateLocal, [Role::Coordonnateur, Role::Admin]],
    'suspendre un Relais' => [Permission::RelaisProfilesSuspend, [Role::Coordonnateur, Role::Admin]],
    'paramétrer la plateforme' => [Permission::SettingsManage, [Role::Admin]],
    'consulter le journal d’audit' => [Permission::AuditView, [Role::Admin]],
]);

it('applique la matrice des droits pour chaque rôle [Annexe A]', function (Permission $permission, array $allowed) {
    foreach (Role::cases() as $role) {
        $user = User::factory()->withRole($role)->create();

        expect($user->hasPermissionTo($permission->value))
            ->toBe(in_array($role, $allowed, true), "{$role->value} → {$permission->value}");
    }
})->with('matrice');

it('ne donne accès à la console qu’aux membres de l’équipe', function () {
    $staff = [Role::Admin, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule, Role::Superviseur, Role::Coordonnateur];

    foreach (Role::cases() as $role) {
        expect(User::factory()->withRole($role)->create()->isStaff())->toBe(in_array($role, $staff, true));
    }
});
