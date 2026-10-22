<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

it('ajoute les en-têtes de sécurité à chaque réponse [§10 Sécurité]', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeaderMissing('X-Powered-By');

    $response->assertHeaderMissing('Content-Security-Policy-Report-Only');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toMatch("/script-src 'self' 'nonce-[^']+'/")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'");
});

it("n'émet HSTS qu'en HTTPS [§10 Sécurité]", function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('sert l’API sous le préfixe versionné /api/v1', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('fait confiance au tunnel ou au répartiteur déclaré : https et hôte public dans les liens', function () {
    config(['jerelais.trusted_proxies' => '127.0.0.1']);
    (fn () => $this->configureTrustedProxies())->call(app()->getProvider(AppServiceProvider::class));

    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'recette.ngrok-free.app'])
        ->assertOk()
        ->assertSee('https://recette.ngrok-free.app/', false);

    TrustProxies::flushState();
});

it('ne fait confiance à aucun proxy par défaut', function () {
    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'pirate.example'])
        ->assertOk()
        ->assertDontSee('pirate.example');
});
