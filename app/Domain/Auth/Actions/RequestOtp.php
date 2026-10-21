<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Models\OtpCode;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Envoie un code de vérification par SMS (MOB-01).
 * Le code est stocké sous forme d'empreinte, valable 5 minutes ; les codes précédents sont annulés.
 * Le résultat est le même que le numéro soit inscrit ou non (pas d'énumération des comptes).
 * La limite par numéro est appliquée ici, commune à l'application et au site (un seul quota de SMS par numéro) ;
 * la limite par adresse IP reste propre à chaque canal.
 */
class RequestOtp
{
    public function __construct(
        private readonly SmsGateway $sms,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ThrottleRequestsException quota d'envoi atteint pour ce numéro
     */
    public function handle(PhoneNumber $phone, ?string $ipAddress = null): void
    {
        $quotaKey = 'otp-send-phone:'.$phone->hash();

        if (RateLimiter::tooManyAttempts($quotaKey, (int) config('jerelais.otp.requests_per_phone'))) {
            throw new ThrottleRequestsException(__('auth.throttle_generic'));
        }

        RateLimiter::hit($quotaKey, (int) config('jerelais.otp.requests_window_minutes') * 60);

        $length = (int) config('jerelais.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $phoneHash = $phone->hash();

        DB::transaction(function () use ($phoneHash, $code, $ipAddress): void {
            OtpCode::query()->where('phone_hash', $phoneHash)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'phone_hash' => $phoneHash,
                'code_hash' => OtpCode::hashCode($phoneHash, $code),
                'expires_at' => now()->addMinutes((int) config('jerelais.otp.ttl_minutes')),
                'ip_address' => $ipAddress,
            ]);

            $this->audit->record('auth.otp.requested', payload: ['phone_ref' => substr($phoneHash, 0, 12)]);
        });

        $this->sms->send($phone, __('auth.otp_sms', [
            'code' => $code,
            'minutes' => config('jerelais.otp.ttl_minutes'),
        ]));
    }
}
