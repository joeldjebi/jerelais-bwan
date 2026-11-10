<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mot de passe obligatoire (MOB-01) : tant qu'il n'est pas défini, l'API répond 403 avec `code: "password_required"`,
 * sauf sur les routes nécessaires pour le définir (profil, définition du mot de passe, appareil, déconnexion).
 */
class EnsurePasswordIsSet
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->password === null) {
            return response()->json(['message' => __('auth.password_required'), 'code' => 'password_required'], 403);
        }

        return $next($request);
    }
}
