<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes HTTP de sécurité appliqués à toutes les réponses (OWASP ASVS V14).
 *
 * La CSP est bloquante. Les scripts ne s'exécutent qu'avec le nonce de la requête ;
 * « unsafe-eval » reste nécessaire aux expressions Alpine de Livewire (aucun script injecté
 * ne peut s'exécuter sans le nonce). Les médias sont déposés et lus directement sur Wasabi.
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
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        // Ne pas annoncer la version de PHP (scan ZAP 10037) ; à doubler par expose_php=Off en production.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $devSources = app()->isLocal() ? ' http://localhost:5173 ws://localhost:5173' : '';
        $reverb = sprintf(' %s://%s:%s', config('reverb.apps.apps.0.options.scheme') === 'https' ? 'wss' : 'ws', config('reverb.apps.apps.0.options.host'), config('reverb.apps.apps.0.options.port'));

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'{$devSources}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$devSources}",
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https://*.wasabisys.com https://*.tile.openstreetmap.org",
            "media-src 'self' blob: https://*.wasabisys.com",
            "connect-src 'self' https://*.wasabisys.com{$reverb}{$devSources}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
