<?php

namespace App\Providers;

use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Audit\Listeners\RecordAuthenticationEvents;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Sms\LogSmsGateway;
use App\Domain\Auth\Support\DemoTwoFactorProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
            default => throw new InvalidArgumentException('Passerelle SMS non configurée : '.config('jerelais.sms.driver')),
        });

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

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $tooMany = fn () => response()->json(['message' => __('auth.throttle_generic')], 429);

        // Envoi de codes SMS : par numéro et par adresse IP (protège le crédit SMS et les comptes).
        RateLimiter::for('otp-request', function (Request $request) use ($tooMany): array {
            $window = (int) config('jerelais.otp.requests_window_minutes');
            $phone = PhoneNumber::tryParse($request->string('phone')->toString());

            return [
                Limit::perMinutes($window, (int) config('jerelais.otp.requests_per_phone'))
                    ->by('otp-phone:'.($phone?->hash() ?? hash('sha256', $request->string('phone')->toString())))
                    ->response($tooMany),
                Limit::perMinutes($window, (int) config('jerelais.otp.requests_per_ip'))
                    ->by('otp-ip:'.$request->ip())
                    ->response($tooMany),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify:'.$request->ip())->response($tooMany));

        RateLimiter::for('password-login', function (Request $request) use ($tooMany): Limit {
            $phone = PhoneNumber::tryParse($request->string('phone')->toString());

            return Limit::perMinute(5)->by('pwd:'.($phone?->hash() ?? 'invalid').'|'.$request->ip())->response($tooMany);
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
