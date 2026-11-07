<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Double authentification obligatoire pour la console et le back-office (ADM-24, §10 Sécurité) :
 * tant qu'elle n'est pas confirmée, l'utilisateur est renvoyé vers la page de sécurité.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('console.security')
                ->with('status', 'two-factor-required');
        }

        return $next($request);
    }
}
