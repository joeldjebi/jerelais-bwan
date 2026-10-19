<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve la console et le back-office aux membres actifs de l'équipe (permission « console.access »).
 */
class EnsureUserIsStaff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->status->canSignIn() && $user->isStaff(), 403);

        return $next($request);
    }
}
