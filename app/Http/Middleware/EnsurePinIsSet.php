<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Code PIN obligatoire (MOB-01) : tant qu'il n'est pas défini, l'API répond 403 avec `code: "pin_required"`,
 * sauf sur les routes nécessaires pour le définir (profil, définition du PIN, appareil, déconnexion).
 */
class EnsurePinIsSet
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->password === null) {
            return response()->json(['message' => __('auth.pin_required'), 'code' => 'pin_required'], 403);
        }

        return $next($request);
    }
}
