<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Déconnecte un appareil : son jeton est détruit immédiatement (déconnexion, perte ou vol — MOB-31).
 */
class RevokeDevice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Device $device, ?User $actor = null, string $reason = 'logout'): void
    {
        if ($device->isRevoked()) {
            return;
        }

        DB::transaction(function () use ($device, $actor, $reason): void {
            if ($device->personal_access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($device->personal_access_token_id)->delete();
            }

            $device->forceFill(['revoked_at' => now(), 'personal_access_token_id' => null, 'fcm_token' => null])->save();

            $this->audit->record('auth.device.revoked', $device, ['reason' => $reason], actor: $actor);
        });
    }
}
