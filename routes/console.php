<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use PragmaRX\Google2FA\Google2FA;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Développement local uniquement : affiche le code de double authentification courant
 * d'un compte de démonstration, pour se connecter à la console sans application d'authentification.
 */
Artisan::command('jerelais:demo-2fa {email}', function (string $email) {
    if (! app()->isLocal()) {
        $this->components->error('Commande réservée à l’environnement local.');

        return 1;
    }

    $user = User::query()->where('email', $email)->first();

    if ($user === null || $user->two_factor_secret === null) {
        $this->components->error('Compte introuvable ou sans double authentification.');

        return 1;
    }

    $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->components->info("Code : {$code} (valable encore ".(30 - time() % 30).' s)');

    return 0;
})->purpose('Affiche le code de double authentification courant d’un compte de démo (local uniquement)');
