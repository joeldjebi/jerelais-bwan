<?php

namespace App\Providers;

use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Audit\Listeners\RecordAuthenticationEvents;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Sms\LogSmsGateway;
use App\Domain\Auth\Sms\MailpitSmsGateway;
use App\Domain\Auth\Support\DemoTwoFactorProvider;
use App\Domain\Candidature\Events\CandidatureDecided;
use App\Domain\Candidature\Listeners\NotifyCandidateOfDecision;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Policies\CandidaturePolicy;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Listeners\AnnounceRelaisStatusChange;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Policies\RelaisItemPolicy;
use App\Domain\Media\Antivirus\ClamAvScanner;
use App\Domain\Media\Antivirus\FakeVirusScanner;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Storage\MultipartStorage;
use App\Domain\Media\Storage\S3MultipartStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Branchements du métier JE RELAIS : services externes, périmètre territorial, audit, limites d'usage.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => match (config('jerelais.sms.driver')) {
            'log' => new LogSmsGateway,
            'mailpit' => new MailpitSmsGateway,
            default => throw new InvalidArgumentException('Passerelle SMS non configurée : '.config('jerelais.sms.driver')),
        });

        $this->app->bind(VirusScanner::class, function () {
            $driver = config('jerelais.antivirus.driver');

            if ($driver === 'fake' && $this->app->isProduction()) {
                throw new InvalidArgumentException('Antivirus factice interdit en production.');
            }

            return match ($driver) {
                'clamav' => new ClamAvScanner(
                    (string) config('jerelais.antivirus.host'),
                    (int) config('jerelais.antivirus.port'),
                    (int) config('jerelais.antivirus.timeout'),
                ),
                'fake' => new FakeVirusScanner,
                default => throw new InvalidArgumentException('Antivirus non configuré : '.$driver),
            };
        });

        $this->app->singleton(MultipartStorage::class, fn () => new S3MultipartStorage('medias'));

        // Une instance par requête : le périmètre calculé ne fuit jamais d'une requête à l'autre.
        $this->app->scoped(CommuneAccess::class);

        // Code de double authentification de démonstration : environnement local uniquement.
        $demoCode = config('jerelais.demo.two_factor_code');

        if (is_string($demoCode) && preg_match('/^\d{6}$/', $demoCode) === 1 && $this->app->environment('local')) {
            $this->app->extend(TwoFactorAuthenticationProvider::class, fn (TwoFactorAuthenticationProvider $provider) => new DemoTwoFactorProvider($provider, $demoCode));
        }
    }

    public function boot(): void
    {
        Event::subscribe(RecordAuthenticationEvents::class);
        Event::listen(CandidatureDecided::class, NotifyCandidateOfDecision::class);
        Event::listen(RelaisStatusChanged::class, AnnounceRelaisStatusChange::class);

        Gate::policy(Candidature::class, CandidaturePolicy::class);
        Gate::policy(RelaisItem::class, RelaisItemPolicy::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $tooMany = fn () => response()->json(['message' => __('auth.throttle_generic')], 429);

        // Envoi de codes SMS par adresse IP ; la limite par numéro, commune au site et à l'application, est dans RequestOtp.
        RateLimiter::for('otp-request', fn (Request $request): Limit => Limit::perMinutes((int) config('jerelais.otp.requests_window_minutes'), (int) config('jerelais.otp.requests_per_ip'))
            ->by('otp-ip:'.$request->ip())
            ->response($tooMany));

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify:'.$request->ip())->response($tooMany));

        RateLimiter::for('password-login', function (Request $request) use ($tooMany): Limit {
            $phone = PhoneNumber::tryParse($request->string('phone')->toString());

            return Limit::perMinute(5)->by('pwd:'.($phone?->hash() ?? 'invalid').'|'.$request->ip())->response($tooMany);
        });

        RateLimiter::for('quiz', fn (Request $request) => Limit::perHour(10)->by('quiz:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))->response($tooMany));

        // Par compte quand l'appel est authentifié ; sinon par adresse IP, avec un plafond plus haut : les opérateurs mobiles
        // partagent une même adresse publique entre de nombreux abonnés (CGNAT, test de charge de l'étape 6).
        RateLimiter::for('api', fn (Request $request) => $request->user() !== null
            ? Limit::perMinute((int) config('jerelais.api.per_user_per_minute'))->by('api-user:'.$request->user()->getAuthIdentifier())
            : Limit::perMinute((int) config('jerelais.api.anonymous_per_ip_per_minute'))->by('api-ip:'.$request->ip()));
    }
}
