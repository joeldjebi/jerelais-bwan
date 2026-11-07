<?php

namespace App\Livewire\Relais\Concerns;

use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Pages de l'espace Relais du site (WEB-03) : réservées à un Relais recruté.
 */
trait InteractsWithRelaisSpace
{
    protected function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function profile(): RelaisProfile
    {
        $profile = RelaisSender::profileOf($this->user());
        abort_if($profile === null, 403);

        return $profile;
    }
}
