<?php

use App\Domain\Access\Enums\Role;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Contribution\Actions\EscalateOverdueRelais;
use App\Domain\Contribution\Actions\ReleaseExpiredLocks;
use App\Domain\Media\Actions\PurgeAbandonedUploads;
use App\Domain\Reporting\Exports\ReportExporter;
use App\Domain\Reporting\Mail\PeriodicReport;
use App\Domain\Reporting\Support\ReportBuilder;
use App\Models\User;
use Aws\S3\Exception\S3Exception;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
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

/*
 * Autorise le navigateur à déposer les morceaux de médias directement sur Wasabi (WEB-04).
 * Ajoute une règle CORS pour les origines données, sans retirer les règles existantes du bucket.
 */
Artisan::command('medias:configure-cors {origins* : ex. http://localhost:8000 https://jerelais.reflet.tv}', function (array $origins) {
    /** @var AwsS3V3Adapter $disk */
    $disk = Storage::disk('medias');
    $client = $disk->getClient();
    $bucket = (string) config('filesystems.disks.medias.bucket');

    try {
        $rules = $client->getBucketCors(['Bucket' => $bucket])['CORSRules'] ?? [];
    } catch (S3Exception) {
        $rules = [];
    }

    $rules = array_values(array_filter($rules, fn (array $rule): bool => ($rule['ID'] ?? '') !== 'jerelais-upload'));
    $rules[] = [
        'ID' => 'jerelais-upload',
        'AllowedOrigins' => array_values($origins),
        'AllowedMethods' => ['PUT', 'GET', 'HEAD'],
        'AllowedHeaders' => ['*'],
        'ExposeHeaders' => ['ETag'],
        'MaxAgeSeconds' => 3000,
    ];

    $client->putBucketCors(['Bucket' => $bucket, 'CORSConfiguration' => ['CORSRules' => $rules]]);
    $this->components->info('Règle CORS « jerelais-upload » enregistrée pour : '.implode(', ', $origins));
})->purpose('Autorise l’envoi direct des médias depuis le navigateur vers Wasabi');

/*
 * Rapports périodiques (§13) : la veille, la semaine passée ou le mois passé, pour tout le réseau.
 */
Artisan::command('reports:send {period=daily : daily, weekly ou monthly}', function (string $period, ReportBuilder $builder, ReportExporter $exporter) {
    $now = CarbonImmutable::now('Africa/Abidjan');
    [$from, $to, $label] = match ($period) {
        'weekly' => [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek(), 'hebdomadaire'],
        'monthly' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(), 'mensuel'],
        default => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'quotidien'],
    };

    $report = $builder->build($from, $to);
    $xlsx = $exporter->xlsx($report);
    $recipients = User::query()->role([Role::Admin->value, Role::RedacteurEnChef->value])
        ->where('status', 'active')->whereNotNull('email')->pluck('email')->all();

    if ($recipients !== []) {
        Mail::to($recipients)->send(new PeriodicReport($label, $exporter->period($report), $exporter->pdf($report, __('Réseau national')), $xlsx));
    }

    @unlink($xlsx);
    $this->components->info('Rapport '.$label.' envoyé à '.count($recipients).' destinataire(s).');
})->purpose('Envoie le rapport de pilotage périodique à l’administration et à la rédaction en chef');

Schedule::command('reports:send daily')->dailyAt('07:00')->timezone('Africa/Abidjan');
Schedule::command('reports:send weekly')->weeklyOn(1, '07:15')->timezone('Africa/Abidjan');
Schedule::command('reports:send monthly')->monthlyOn(1, '07:30')->timezone('Africa/Abidjan');
