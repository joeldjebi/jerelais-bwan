<?php

use App\Domain\Auth\Support\DemoTwoFactorProvider;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

it('n’accepte jamais le code de démonstration hors environnement local', function () {
    config(['jerelais.demo.two_factor_code' => '123456']);

    $provider = app(TwoFactorAuthenticationProvider::class);

    expect($provider)->not->toBeInstanceOf(DemoTwoFactorProvider::class)
        ->and($provider->verify($provider->generateSecretKey(), '123456'))->toBeFalse();
});

it('accepte le code de démonstration et les vrais codes quand il est branché (local)', function () {
    $real = app(TwoFactorAuthenticationProvider::class);
    $demo = new DemoTwoFactorProvider($real, '123456');
    $secret = $real->generateSecretKey();

    expect($demo->verify($secret, '123456'))->toBeTrue()
        ->and($demo->verify($secret, '654321'))->toBe($real->verify($secret, '654321'))
        ->and($demo->verify($secret, (new Google2FA)->getCurrentOtp($secret)))->toBeTrue();
});
