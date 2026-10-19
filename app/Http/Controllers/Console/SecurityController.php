<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Activation de la double authentification (obligatoire pour l'équipe).
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('console.security', [
            'user' => $user,
            'pendingConfirmation' => $user->hasPendingTwoFactorConfirmation(),
            'showRecoveryCodes' => session('status') === 'two-factor-authentication-confirmed',
        ]);
    }
}
