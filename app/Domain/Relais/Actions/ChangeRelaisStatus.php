<?php

namespace App\Domain\Relais\Actions;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Relais\Notifications\RelaisStatusChangedByCoordinator;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Gestion d'un Relais (COO-04, ADM-22) : suspension ou radiation motivée (l'administration est informée
 * quand la décision vient d'un coordonnateur), réintégration, transfert de commune (administration).
 */
class ChangeRelaisStatus
{
    public function __construct(
        private readonly SmsGateway $sms,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(RelaisProfile $profile, RelaisStatus $status, User $actor, string $reason): RelaisProfile
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('relais_profils.errors.reason_required'));
        }

        if ($status === RelaisStatus::Active && ! $actor->hasPermissionTo(Permission::SettingsManage->value) && $profile->status === RelaisStatus::StruckOff) {
            throw new InvalidArgumentException(__('relais_profils.errors.reinstate_admin_only'));
        }

        if ($status === $profile->status) {
            return $profile;
        }

        $from = $profile->status;

        DB::transaction(function () use ($profile, $status, $actor, $reason, $from): void {
            $profile->forceFill([
                'status' => $status,
                'suspended_at' => $status === RelaisStatus::Active ? null : now(),
            ])->save();

            $this->audit->record('relais.profile_'.$status->value, $profile, [
                'from' => $from->value,
                'reason' => $reason,
            ], actor: $actor, communeId: $profile->commune_id);
        });

        if (! $actor->hasPermissionTo(Permission::SettingsManage->value)) {
            Notification::send(
                User::query()->role(Role::Admin->value)->get(),
                new RelaisStatusChangedByCoordinator($profile, $status, $actor, $reason),
            );
        }

        $phone = $profile->user->phoneNumber();

        if ($phone !== null) {
            $this->sms->send($phone, __("relais_profils.sms.{$status->value}"));
        }

        return $profile;
    }

    public function transfer(RelaisProfile $profile, Commune $commune, User $admin, string $reason): RelaisProfile
    {
        $from = $profile->commune_id;

        $profile->forceFill(['commune_id' => $commune->id, 'localite_id' => null])->save();
        $this->audit->record('relais.profile_transferred', $profile, ['from' => $from, 'to' => $commune->id, 'reason' => trim($reason)], actor: $admin, communeId: $commune->id);

        return $profile;
    }
}
