<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes HTTP de sécurité appliqués à toutes les réponses (OWASP ASVS V14).
 *
 * La CSP est émise en mode « report-only » tant que Livewire/Alpine n'ont pas été
 * validés en mode compatible CSP ; elle devient bloquante à l'étape 5 (durcissement).
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $devSources = app()->isLocal() ? ' http://localhost:5173 ws://localhost:5173' : '';
        $reverb = sprintf(' %s://%s:%s', config('reverb.apps.apps.0.options.scheme') === 'https' ? 'wss' : 'ws', config('reverb.apps.apps.0.options.host'), config('reverb.apps.apps.0.options.port'));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$devSources}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$devSources}",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https://*.wasabisys.com https://*.tile.openstreetmap.org",
            "media-src 'self' blob: https://*.wasabisys.com",
            "connect-src 'self'{$reverb}{$devSources}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
