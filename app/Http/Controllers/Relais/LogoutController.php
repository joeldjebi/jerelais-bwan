<?php

namespace App\Http\Controllers\Relais;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Déconnexion de l'espace candidat ou Relais du site.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $audit->record('auth.logout', $user, ['channel' => 'web'], actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
