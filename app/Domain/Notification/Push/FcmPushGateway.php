<?php

namespace App\Domain\Notification\Push;

use App\Domain\Notification\Contracts\PushGateway;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, API HTTP v1 (PUSH_DRIVER=fcm).
 *
 * Authentification par le compte de service du projet Firebase : jeton OAuth 2 signé localement (RS256), mis en
 * cache 55 minutes. Ni le jeton de l'appareil, ni la clé, ni le jeton d'accès ne sont écrits dans les journaux.
 */
class FcmPushGateway implements PushGateway
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Codes d'erreur FCM qui signifient que le jeton de l'appareil ne servira plus. */
    private const INVALID_TOKEN_CODES = ['UNREGISTERED', 'SENDER_ID_MISMATCH'];

    /**
     * @param  array{project_id?: string, client_email?: string, private_key?: string, token_uri?: string}  $credentials
     */
    public function __construct(private readonly array $credentials, private readonly int $timeout = 10) {}

    public static function fromConfig(): self
    {
        $path = (string) config('services.fcm.credentials');
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        $credentials = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return new self(is_array($credentials) ? $credentials : [], (int) config('services.fcm.timeout'));
    }

    public function projectId(): ?string
    {
        return $this->credentials['project_id'] ?? null;
    }

    public function isConfigured(): bool
    {
        return isset($this->credentials['project_id'], $this->credentials['client_email'], $this->credentials['private_key']);
    }

    public function send(string $token, PushMessage $message): PushResult
    {
        return $this->sendDetailed($token, $message)['result'];
    }

    /**
     * Envoi avec la réponse de FCM (commande push:test, diagnostic).
     *
     * @return array{result: PushResult, http_status: int|null, error: string|null}
     */
    public function sendDetailed(string $token, PushMessage $message): array
    {
        if (! $this->isConfigured()) {
            Log::warning('[Push FCM] clé du compte de service absente ou invalide (FIREBASE_CREDENTIALS)');

            return ['result' => PushResult::Failed, 'http_status' => null, 'error' => 'Clé du compte de service Firebase absente ou invalide (FIREBASE_CREDENTIALS).'];
        }

        try {
            $response = $this->post($token, $message);

            // Jeton d'accès refusé (clé révoquée entre-temps) : un nouvel essai avec un jeton neuf.
            if ($response->status() === 401) {
                Cache::forget($this->cacheKey());
                $response = $this->post($token, $message);
            }
        } catch (ConnectionException|RuntimeException $exception) {
            Log::warning('[Push FCM] envoi impossible', ['error' => $exception->getMessage()]);

            return ['result' => PushResult::Failed, 'http_status' => null, 'error' => $exception->getMessage()];
        }

        if ($response->successful()) {
            return ['result' => PushResult::Sent, 'http_status' => $response->status(), 'error' => null];
        }

        $code = $this->errorCode($response);
        // INVALID_ARGUMENT ne vise le jeton que si FCM le dit : sinon, une erreur de contenu effacerait tous les jetons.
        $badToken = $code === 'INVALID_ARGUMENT' && str_contains(mb_strtolower((string) $response->json('error.message')), 'registration token');
        $result = $badToken || in_array($code, self::INVALID_TOKEN_CODES, true) || $response->status() === 404 ? PushResult::InvalidToken : PushResult::Failed;

        if ($result === PushResult::Failed) {
            Log::warning('[Push FCM] refusé', ['http_status' => $response->status(), 'code' => $code]);
        }

        return ['result' => $result, 'http_status' => $response->status(), 'error' => $code ?? (string) $response->json('error.message')];
    }

    /**
     * @throws ConnectionException
     */
    private function post(string $token, PushMessage $message): Response
    {
        $payload = [
            'token' => $token,
            'notification' => ['title' => $message->title, 'body' => $message->body],
            'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
            'apns' => ['headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['sound' => 'default']]],
        ];

        if ($message->data !== []) {
            $payload['data'] = $message->data;
        }

        return Http::timeout($this->timeout)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post('https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send', ['message' => $payload]);
    }

    private function errorCode(Response $response): ?string
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode']) && is_string($detail['errorCode'])) {
                return $detail['errorCode'];
            }
        }

        $status = $response->json('error.status');

        return is_string($status) ? $status : null;
    }

    /**
     * Jeton d'accès OAuth 2 du compte de service (valable une heure, gardé 55 minutes).
     *
     * @throws ConnectionException
     * @throws RuntimeException clé illisible ou jeton refusé par Google
     */
    private function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenUri = $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode([
                'iss' => $this->credentials['client_email'] ?? '',
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $key = openssl_pkey_get_private($this->credentials['private_key'] ?? '');

        if ($key === false || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Clé privée du compte de service Firebase illisible.');
        }

        $response = Http::asForm()->timeout($this->timeout)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', [...$segments, self::base64Url($signature)]),
        ]);

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken)) {
            throw new RuntimeException('Google a refusé le compte de service Firebase (HTTP '.$response->status().', '.($response->json('error') ?? 'réponse inattendue').').');
        }

        Cache::put($this->cacheKey(), $accessToken, now()->addMinutes(55));

        return $accessToken;
    }

    private function cacheKey(): string
    {
        return 'fcm:access-token:'.hash('sha256', (string) ($this->credentials['client_email'] ?? ''));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
