<?php

namespace App\Domain\Access\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Actions\RevokeDevice;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Suspend ou réactive un compte (ADM-21, ADM-22). Une suspension coupe immédiatement
 * tous les appareils et sessions du compte.
 */
class ChangeUserStatus
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RevokeDevice $revokeDevice,
    ) {}

    public function handle(User $user, UserStatus $status, User $actor, ?string $reason = null): void
    {
        if ($user->is($actor)) {
            throw new InvalidArgumentException('Vous ne pouvez pas modifier le statut de votre propre compte.');
        }

        if ($user->status === $status) {
            return;
        }

        DB::transaction(function () use ($user, $status, $actor, $reason): void {
            $user->forceFill(['status' => $status])->save();

            if (! $status->canSignIn()) {
                foreach ($user->devices()->whereNull('revoked_at')->get() as $device) {
                    $this->revokeDevice->handle($device, $actor, 'account_suspended');
                }

                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $this->audit->record(
                $status->canSignIn() ? 'users.reactivated' : 'users.suspended',
                $user,
                array_filter(['reason' => $reason]),
                $actor,
            );
        });
    }
}
