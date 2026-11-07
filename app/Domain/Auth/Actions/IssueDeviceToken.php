<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Models\Device;
use App\Models\User;

/**
 * Crée un appareil et son jeton Sanctum dédié (un jeton par appareil, révocable à distance — MOB-31).
 */
class IssueDeviceToken
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, DeviceInfo $info, string $method, bool $isNewUser = false): IssuedToken
    {
        if ($user->isStaff()) {
            throw AuthenticationRefused::staffUseConsole();
        }

        $device = new Device([
            'name' => $info->name,
            'platform' => $info->platform,
            'app_version' => $info->appVersion,
            'last_seen_at' => now(),
        ]);
        $device->user()->associate($user);
        $device->save();

        $token = $user->createToken($device->uuid, ['*'], now()->addMinutes((int) config('sanctum.expiration')));
        $device->update(['personal_access_token_id' => $token->accessToken->getKey()]);

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('auth.login', $device, ['method' => $method, 'platform' => $info->platform->value], actor: $user);

        return new IssuedToken($user, $device, $token->plainTextToken, $isNewUser);
    }
}
