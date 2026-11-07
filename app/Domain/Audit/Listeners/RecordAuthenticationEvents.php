<?php

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Inscrit dans l'audit les connexions et la sécurité des comptes de la console (ADM-25).
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->save();
            $this->audit->record('auth.login', payload: ['method' => 'web', 'guard' => $event->guard], actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->record('auth.login.failed', payload: ['method' => 'web', 'guard' => $event->guard], actor: $event->user instanceof User ? $event->user : null);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->record('auth.login.locked_out', payload: ['method' => 'web']);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', payload: ['method' => 'web'], actor: $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record('auth.2fa.enabled', actor: $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->record('auth.2fa.disabled', actor: $event->user);
    }

    public function handleRecoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->audit->record('auth.2fa.recovery_code_used', actor: $event->user instanceof User ? $event->user : null);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            Logout::class => 'handleLogout',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            RecoveryCodeReplaced::class => 'handleRecoveryCodeUsed',
        ];
    }
}
