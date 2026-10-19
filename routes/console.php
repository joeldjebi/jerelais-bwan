<?php

use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Contribution\Actions\EscalateOverdueRelais;
use App\Domain\Contribution\Actions\ReleaseExpiredLocks;
use App\Domain\Media\Actions\PurgeAbandonedUploads;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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

/*
 * Traitements planifiés du cycle de vie des relais (§8.2, COO-17).
 */
Artisan::command('relais:escalate', function (EscalateOverdueRelais $escalate) {
    $this->components->info($escalate->handle().' relais escaladé(s).');
})->purpose('Escalade les relais non traités dans le délai cible');

Artisan::command('relais:release-locks', function (ReleaseExpiredLocks $release) {
    $this->components->info($release->handle().' verrou(s) expiré(s) libéré(s).');
})->purpose('Remet dans la file les relais dont le verrou a expiré');

Artisan::command('medias:purge-abandoned', function (PurgeAbandonedUploads $purge) {
    $this->components->info($purge->handle().' envoi(s) abandonné(s) annulé(s).');
})->purpose('Annule les envois de médias jamais terminés');

Artisan::command('appels:close-expired', function (PublishAppel $appels) {
    $expired = AppelRelais::query()->where('status', 'open')->where('deadline_at', '<=', now())->get();
    $expired->each(fn ($appel) => $appels->close($appel));
    $this->components->info($expired->count().' appel(s) clos.');
})->purpose('Clôt les appels à relais dont la date limite est passée');

Schedule::command('appels:close-expired')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('relais:release-locks')->everyMinute()->withoutOverlapping();
Schedule::command('relais:escalate')->everyMinute()->withoutOverlapping();
Schedule::command('medias:purge-abandoned')->hourly()->withoutOverlapping();
