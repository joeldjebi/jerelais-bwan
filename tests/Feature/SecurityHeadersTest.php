<?php

it('ajoute les en-têtes de sécurité à chaque réponse [§10 Sécurité]', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

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
