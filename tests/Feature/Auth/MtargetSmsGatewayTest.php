<?php

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Sms\MtargetSmsGateway;
use App\Domain\Auth\Sms\NotificationSms;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const MTARGET_URL = 'https://api-public-2.mtarget.fr/messages';

beforeEach(function () {
    config([
        'jerelais.sms.driver' => 'mtarget',
        'jerelais.otp.fixed_code' => '',
        'services.mtarget.url' => MTARGET_URL,
        'services.mtarget.username' => 'compte-jerelais',
        'services.mtarget.password' => 'secret-mtarget',
        'services.mtarget.sender' => 'JERELAIS',
        'services.mtarget.timeout' => 5,
        'services.mtarget.verify_ssl' => true,
        'services.mtarget.dlr_token' => 'jeton-dlr',
    ]);
    app()->forgetInstance(SmsGateway::class);
});

function mtargetAccepts(): void
{
    Http::fake([MTARGET_URL => Http::response(['results' => [['msisdn' => '+2250707070707', 'smscount' => '1', 'code' => '0', 'reason' => 'ACCEPTED', 'ticket' => 'T-123']]])]);
}

it('envoie le SMS en formulaire avec le numéro international, l’expéditeur et les identifiants', function () {
    mtargetAccepts();

    app(SmsGateway::class)->send(PhoneNumber::parse('07 07 07 07 07'), 'Bonjour');

    Http::assertSent(fn (Request $request): bool => $request->url() === MTARGET_URL
        && $request->method() === 'POST'
        && $request->isForm()
        && $request['username'] === 'compte-jerelais'
        && $request['password'] === 'secret-mtarget'
        && $request['msisdn'] === '+2250707070707'
        && $request['msg'] === 'Bonjour'
        && $request['sender'] === 'JERELAIS'
        && ! isset($request['remoteid']));
});

it('refuse un SMS que MTarget n’a pas accepté, ou une erreur HTTP', function (mixed $response) {
    Http::fake([MTARGET_URL => $response]);

    app(SmsGateway::class)->send(PhoneNumber::parse('0707070707'), 'Bonjour');
})->with([
    'motif refusé' => fn () => Http::response(['results' => [['code' => '3', 'reason' => 'INVALID_MSISDN']]]),
    'erreur HTTP' => fn () => Http::response('Unauthorized', 401),
    'réponse inattendue' => fn () => Http::response('pas du JSON'),
])->throws(SmsNotSent::class);

it('refuse d’envoyer sans identifiants, sans appeler MTarget', function () {
    Http::fake();
    config(['services.mtarget.password' => '']);
    app()->forgetInstance(SmsGateway::class);

    expect(fn () => app(SmsGateway::class)->send(PhoneNumber::parse('0707070707'), 'Bonjour'))
        ->toThrow(SmsNotSent::class, 'Les identifiants MTarget ne sont pas configurés.');

    Http::assertNothingSent();
});

it('ne journalise jamais le mot de passe MTarget ni le code envoyé', function () {
    Http::fake([MTARGET_URL => Http::response(['results' => [['code' => '1', 'reason' => 'REFUSED']]])]);
    $logged = [];
    Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertServiceUnavailable();

    expect(implode("\n", $logged))->not->toContain('secret-mtarget')->not->toContain('compte-jerelais');
});

it('envoie un code aléatoire par MTarget à l’inscription (plus de 1234)', function () {
    mtargetAccepts();

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();

    $sent = Http::recorded()[0][0];
    preg_match('/\b(\d{4})\b/', $sent['msg'], $matches);

    expect($matches[1] ?? null)->not->toBeNull()
        ->and($sent['msg'])->toContain('JE RELAIS : votre code de vérification est');

    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => $matches[1]])->assertOk();
});

it('répond 503 et annule le code quand MTarget refuse l’envoi', function () {
    Http::fake([MTARGET_URL => Http::response('Service Unavailable', 503)]);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'L’envoi du SMS a échoué. Réessayez dans quelques instants.');

    expect(OtpCode::query()->whereNull('consumed_at')->count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp.sms_failed']);
});

it('protège le webhook des accusés de réception par jeton', function () {
    $this->postJson(route('api.v1.sms.dlr'), ['ticket' => 'T-123', 'status' => 'DELIVERED'])->assertForbidden();
    $this->postJson(route('api.v1.sms.dlr', ['token' => 'mauvais']), ['ticket' => 'T-123'])->assertForbidden();
    $this->postJson(route('api.v1.sms.dlr', ['token' => 'jeton-dlr']), ['ticket' => 'T-123', 'status' => 'DELIVERED', 'msisdn' => '+2250707070707'])->assertNoContent();

    config(['services.mtarget.dlr_token' => '']);
    $this->postJson(route('api.v1.sms.dlr', ['token' => '']))->assertNotFound();
});

it('normalise le numéro au format international', function (string $raw, string $expected) {
    expect(MtargetSmsGateway::formatMsisdn($raw))->toBe($expected);
})->with([
    ['2250707070707', '+2250707070707'],
    [' +225 07 07-07.07 (07) ', '+2250707070707'],
]);

it('teste l’envoi en ligne de commande sans afficher les identifiants', function () {
    mtargetAccepts();

    $this->artisan('sms:test', ['msisdn' => '+2250707070707'])
        ->expectsOutputToContain('ACCEPTED')
        ->doesntExpectOutputToContain('secret-mtarget')
        ->assertSuccessful();
});

it('n’interrompt pas l’action quand un SMS d’information est refusé', function () {
    Http::fake([MTARGET_URL => Http::response('Service Unavailable', 503)]);

    $sent = NotificationSms::send(app(SmsGateway::class), PhoneNumber::parse('0707070707'), 'Votre relais est validé.', 'relais_item');

    expect($sent)->toBeFalse();
});
