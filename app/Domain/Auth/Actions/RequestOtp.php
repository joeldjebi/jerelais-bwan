<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Domain\Auth\Exceptions\SmsQuotaReached;
use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Support\OtpPolicy;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Envoie un code de vérification par SMS (MOB-01).
 * Le code est stocké sous forme d'empreinte, valable 5 minutes ; les codes précédents sont annulés.
 * Le résultat est le même que le numéro soit inscrit ou non (pas d'énumération des comptes).
 * La limite par numéro est appliquée ici, commune à l'application et au site (un seul quota de SMS par numéro) ;
 * la limite par adresse IP reste propre à chaque canal.
 * Protection contre la fraude aux SMS : seuls les indicatifs autorisés (Côte d'Ivoire par défaut) reçoivent un code,
 * et un plafond horaire global borne le nombre de SMS envoyés.
 */
class RequestOtp
{
    public function __construct(
        private readonly SmsGateway $sms,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException numéro hors des indicatifs autorisés
     * @throws ThrottleRequestsException quota d'envoi atteint pour ce numéro
     * @throws SmsQuotaReached plafond horaire global atteint
     * @throws SmsNotSent le fournisseur de SMS n'a pas accepté le message
     */
    public function handle(PhoneNumber $phone, ?string $ipAddress = null): void
    {
        if (! self::isAllowed($phone)) {
            throw ValidationException::withMessages(['phone' => __('auth.otp_country_not_allowed')]);
        }

        $quotaKey = 'otp-send-phone:'.$phone->hash();

        if (RateLimiter::tooManyAttempts($quotaKey, (int) config('jerelais.otp.requests_per_phone'))) {
            throw new ThrottleRequestsException(__('auth.throttle_generic'));
        }

        RateLimiter::hit($quotaKey, (int) config('jerelais.otp.requests_window_minutes') * 60);

        $fixedCode = OtpPolicy::fixedCodeFor($phone);

        if ($fixedCode === null) {
            $this->ensureGlobalQuota();
        }

        $length = (int) config('jerelais.otp.length');
        $code = $fixedCode ?? str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $phoneHash = $phone->hash();

        DB::transaction(function () use ($phoneHash, $code, $ipAddress, $fixedCode): void {
            OtpCode::query()->where('phone_hash', $phoneHash)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone_hash' => $phoneHash,
                'code_hash' => OtpCode::hashCode($phoneHash, $code),
                'expires_at' => now()->addMinutes((int) config('jerelais.otp.ttl_minutes')),
                'ip_address' => $ipAddress,
            ]);

            $this->audit->record('auth.otp.requested', payload: ['phone_ref' => substr($phoneHash, 0, 12), 'fixed_code' => $fixedCode !== null]);
        });

        // Code fixe transitoire : rien à envoyer, l'équipe communique le code aux testeurs.
        if ($fixedCode !== null) {
            return;
        }

        try {
            $this->sms->send($phone, __('auth.otp_sms', [
                'code' => $code,
                'minutes' => config('jerelais.otp.ttl_minutes'),
            ]));
        } catch (SmsNotSent $exception) {
            // Code jamais reçu : on l'annule pour ne laisser aucun code valide en circulation.
            OtpCode::query()->where('phone_hash', $phoneHash)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $this->audit->record('auth.otp.sms_failed', payload: ['phone_ref' => substr($phoneHash, 0, 12)]);

            throw $exception;
        }
    }

    /**
     * Le numéro peut-il recevoir un code ? (indicatifs de jerelais.otp.allowed_prefixes ; liste vide : tous)
     */
    public static function isAllowed(PhoneNumber $phone): bool
    {
        /** @var list<string> $prefixes */
        $prefixes = config('jerelais.otp.allowed_prefixes', []);

        return $prefixes === [] || collect($prefixes)->contains(fn (string $prefix): bool => str_starts_with($phone->e164, $prefix));
    }

    /**
     * Plafond horaire de SMS de code pour toute la plateforme. Une seule alerte par heure dans le journal.
     *
     * @throws SmsQuotaReached
     */
    private function ensureGlobalQuota(): void
    {
        $limit = (int) config('jerelais.otp.global_per_hour');

        if ($limit <= 0) {
            return;
        }

        if (RateLimiter::tooManyAttempts('otp-send-global', $limit)) {
            if (Cache::add('otp-send-global-alerted', true, 3600)) {
                Log::warning('Plafond horaire de SMS de code atteint', ['limit' => $limit]);
                $this->audit->record('auth.otp.global_cap_reached', payload: ['limit' => $limit]);
            }

            throw new SmsQuotaReached('Plafond horaire de SMS atteint.');
        }

        RateLimiter::hit('otp-send-global', 3600);
    }
}
