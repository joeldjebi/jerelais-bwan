<?php

namespace App\Http\Controllers\Public;

use App\Domain\Relais\Models\RelaisProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Page ouverte en scannant le QR code d'un badge : confirme qu'il s'agit d'un Relais et de son statut.
 * N'affiche que le prénom, l'initiale, la commune et le numéro (aucune donnée sensible).
 */
class BadgeVerificationController extends Controller
{
    public function __invoke(string $token): View
    {
        $profile = strlen($token) === 40
            ? RelaisProfile::withoutCommuneRestriction()->with('commune')->where('badge_token', $token)->first()
            : null;

        return view('public.badge', ['profile' => $profile]);
    }
}
