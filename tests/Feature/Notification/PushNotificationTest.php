<?php

use App\Domain\Auth\Models\Device;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Notification\Enums\PushResult;
use App\Domain\Notification\Push\FcmPushGateway;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->push = fakePush();
    $this->relais = RelaisItem::factory()->create(['commune_id' => Commune::factory()->create()->id, 'title' => 'Inondation à Niangon']);
    $this->user = $this->relais->user;
});

function pushDevice(User $user, array $attributes = []): Device
{
    return Device::query()->create([
        'user_id' => $user->id,
        'name' => 'Tecno Spark 10',
        'platform' => 'android',
        'fcm_token' => 'jeton-fcm-1',
        ...$attributes,
    ]);
}

it('envoie une notification push au Relais quand son relais est validé, sans SMS [MOB-21]', function () {
    pushDevice($this->user);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');

    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['token'])->toBe('jeton-fcm-1')
        ->and($this->push->sent[0]['message']->title)->toBe('Relais validé')
        ->and($this->push->sent[0]['message']->body)->toContain('Inondation à Niangon')
        ->and($this->push->sent[0]['message']->data)->toBe(['type' => 'relais.validated', 'relais' => $this->relais->uuid])
        ->and($this->sms->sent)->toBeEmpty();
});

it('notifie chaque décision sur le relais : refus, complément, sélection, diffusion [MOB-21]', function (string $status, string $title) {
    pushDevice($this->user);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', $status);

    expect($this->push->sent[0]['message']->title)->toBe($title)
        ->and($this->push->sent[0]['message']->data['type'])->toBe("relais.{$status}");
})->with([
    ['rejected', 'Relais non retenu'],
    ['complement_requested', 'Une question sur votre relais'],
    ['selected', 'Relais retenu par la rédaction'],
    ['broadcast', 'Votre relais est passé à l’antenne'],
]);

it('ne notifie pas les étapes internes du relais [MOB-21]', function () {
    pushDevice($this->user);

    RelaisStatusChanged::dispatch($this->relais, 'received', 'in_review');

    expect($this->push->sent)->toBeEmpty()->and($this->sms->sent)->toBeEmpty();
});

it('envoie un SMS de secours au Relais sans appareil joignable [MOB-21]', function () {
    pushDevice($this->user, ['fcm_token' => null]);
    pushDevice($this->user, ['revoked_at' => now(), 'fcm_token' => 'jeton-revoque']);
    pushDevice($this->user, ['platform' => 'web', 'fcm_token' => 'jeton-web']);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');

    expect($this->push->sent)->toBeEmpty()
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['message'])->toContain('est validé');
});

it('oublie un jeton refusé par Firebase et envoie alors le SMS de secours [MOB-21]', function () {
    $device = pushDevice($this->user, ['fcm_token' => 'jeton-expire']);
    $this->push->invalidTokens = ['jeton-expire'];

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'rejected');

    expect($device->refresh()->fcm_token)->toBeNull()
        ->and($this->sms->sent)->toHaveCount(1);
});

it('notifie tous les appareils connectés du Relais [MOB-21]', function () {
    pushDevice($this->user);
    pushDevice($this->user, ['name' => 'iPhone', 'platform' => 'ios', 'fcm_token' => 'jeton-fcm-2']);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');

    expect(collect($this->push->sent)->pluck('token')->all())->toEqualCanonicalizing(['jeton-fcm-1', 'jeton-fcm-2']);
});

it('n’envoie aucun push quand les notifications sont désactivées : SMS uniquement [MOB-21]', function () {
    config(['jerelais.push.driver' => 'none']);
    pushDevice($this->user);

    RelaisStatusChanged::dispatch($this->relais, 'in_review', 'validated');

    expect($this->push->sent)->toBeEmpty()->and($this->sms->sent)->toHaveCount(1);
});

/**
 * Clé de compte de service générée pour le test (jamais une vraie clé).
 *
 * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}
 */
function fakeFirebaseCredentials(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);

    return ['project_id' => 'jerelais-test', 'client_email' => 'push@jerelais-test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token'];
}

it('envoie par l’API HTTP v1 de Firebase avec un jeton du compte de service [MOB-21]', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3599]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/jerelais-test/messages/1']),
    ]);

    $result = (new FcmPushGateway(fakeFirebaseCredentials()))->send('jeton-fcm-1', new PushMessage('Relais validé', 'Merci !', ['type' => 'relais.validated', 'relais' => 'abc']));

    expect($result)->toBe(PushResult::Sent);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && count(explode('.', $request['assertion'])) === 3);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/jerelais-test/messages:send'
        && $request->hasHeader('Authorization', 'Bearer jeton-acces')
        && $request['message']['token'] === 'jeton-fcm-1'
        && $request['message']['notification'] === ['title' => 'Relais validé', 'body' => 'Merci !']
        && $request['message']['data'] === ['type' => 'relais.validated', 'relais' => 'abc']);
});

it('reconnaît un jeton d’appareil désinstallé et une panne de Firebase [MOB-21]', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces']),
        'fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]], 404)
            ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503)
            ->push(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'Invalid JSON payload received.']], 400),
    ]);

    $gateway = new FcmPushGateway(fakeFirebaseCredentials());
    $message = new PushMessage('Titre', 'Texte');

    expect($gateway->send('jeton-1', $message))->toBe(PushResult::InvalidToken)
        ->and($gateway->send('jeton-2', $message))->toBe(PushResult::Failed)
        ->and($gateway->send('jeton-3', $message))->toBe(PushResult::Failed);
});

it('refuse proprement d’envoyer sans clé de compte de service [MOB-21]', function () {
    Http::fake();

    expect((new FcmPushGateway([]))->send('jeton-1', new PushMessage('Titre', 'Texte')))->toBe(PushResult::Failed);

    Http::assertNothingSent();
});
