<?php

namespace App\Domain\Auth\Support;

use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Développement local uniquement : accepte, en plus des codes TOTP réels, un code de démonstration fixe
 * (DEMO_TWO_FACTOR_CODE). N'est jamais branché hors de l'environnement « local » (voir DomainServiceProvider).
 */
class DemoTwoFactorProvider implements TwoFactorAuthenticationProvider
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly string $demoCode,
    ) {}

    public function generateSecretKey()
    {
        return $this->provider->generateSecretKey();
    }

    public function qrCodeUrl($companyName, $companyEmail, $secret)
    {
        return $this->provider->qrCodeUrl($companyName, $companyEmail, $secret);
    }

    public function verify($secret, $code)
    {
        return hash_equals($this->demoCode, (string) $code) || $this->provider->verify($secret, $code);
    }
}
