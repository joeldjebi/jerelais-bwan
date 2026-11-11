<?php

namespace App\Domain\Auth\Sms;

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\SmsNotSent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des SMS par l'API HTTP de MTarget (SMS_DRIVER=mtarget).
 *
 * Réponse attendue : {"results":[{"msisdn":"+225…","smscount":"1","code":"0","reason":"ACCEPTED","ticket":"…"}]}.
 * « ACCEPTED » signifie seulement que MTarget a pris le message en charge ; la livraison se confirme par les
 * accusés de réception (webhook POST /api/v1/sms/dlr). Les identifiants, l'URL complète et le corps de la requête
 * ne sont jamais écrits dans les journaux.
 */
class MtargetSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $url,
        private readonly string $username,
        private readonly string $password,
        private readonly string $defaultSender,
        private readonly int $timeout = 30,
        private readonly bool $verifySsl = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            url: (string) config('services.mtarget.url'),
            username: (string) config('services.mtarget.username'),
            password: (string) config('services.mtarget.password'),
            defaultSender: (string) config('services.mtarget.sender'),
            timeout: (int) config('services.mtarget.timeout'),
            verifySsl: (bool) config('services.mtarget.verify_ssl'),
        );
    }

    /**
     * @throws SmsNotSent message refusé par MTarget, erreur HTTP ou réseau, identifiants absents
     */
    public function send(PhoneNumber $to, string $message): void
    {
        $result = $this->sendDetailed($message, $to->e164);
        $first = $result['results'][0] ?? [];

        if ($result['http_status'] !== 200 || ! self::isAccepted($first)) {
            Log::warning('[SMS MTarget] refusé', [
                'to' => $to->masked(),
                'http_status' => $result['http_status'],
                'code' => $first['code'] ?? null,
                'reason' => $first['reason'] ?? null,
            ]);

            throw new SmsNotSent('MTarget n’a pas accepté le SMS (HTTP '.$result['http_status'].', '.($first['reason'] ?? 'réponse inattendue').').');
        }

        Log::info('[SMS MTarget] accepté', ['to' => $to->masked(), 'ticket' => $first['ticket'] ?? null, 'smscount' => $first['smscount'] ?? null]);
    }

    /**
     * Envoi brut, sans interprétation de la réponse (commande sms:test, diagnostic).
     *
     * @return array{http_status: int, raw_body: string, body: array<string, mixed>|null, results: list<array<string, mixed>>, msisdn: string, sender: string}
     *
     * @throws SmsNotSent identifiants absents ou erreur réseau
     */
    public function sendDetailed(string $message, string $msisdn, ?string $sender = null, ?string $remoteId = null): array
    {
        if ($this->username === '' || $this->password === '') {
            throw new SmsNotSent('Les identifiants MTarget ne sont pas configurés.');
        }

        $msisdn = self::formatMsisdn($msisdn);
        $sender ??= $this->defaultSender;

        $params = array_filter([
            'username' => $this->username,
            'password' => $this->password,
            'msisdn' => $msisdn,
            'msg' => $message,
            'sender' => $sender,
            'remoteid' => $remoteId,
        ], fn (?string $value): bool => $value !== null);

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->withOptions(['verify' => $this->verifySsl])
                ->post($this->url, $params);
        } catch (ConnectionException $exception) {
            // Le message de Guzzle peut contenir l'URL : on ne garde que la nature de l'erreur.
            Log::warning('[SMS MTarget] injoignable', ['to' => PhoneNumber::tryParse($msisdn)?->masked(), 'error' => class_basename($exception)]);

            throw new SmsNotSent('MTarget est injoignable (erreur réseau ou délai dépassé).', previous: $exception);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : null;
        $results = is_array($body['results'] ?? null) ? array_values(array_filter($body['results'], 'is_array')) : [];

        return [
            'http_status' => $response->status(),
            'raw_body' => $response->body(),
            'body' => $body,
            'results' => $results,
            'msisdn' => $msisdn,
            'sender' => $sender,
        ];
    }

    /**
     * Format international attendu par MTarget : +2250700000000.
     */
    public static function formatMsisdn(string $msisdn): string
    {
        $msisdn = (string) preg_replace('/[\s().-]+/', '', trim($msisdn));

        return str_starts_with($msisdn, '+') ? $msisdn : '+'.$msisdn;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function isAccepted(array $result): bool
    {
        return (string) ($result['code'] ?? '') === '0' && strtoupper((string) ($result['reason'] ?? '')) === 'ACCEPTED';
    }
}
