<?php

use App\Domain\Auth\Contracts\SmsGateway;
use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\Mail;

it('livre les SMS de recette locale dans Mailpit, adressés au numéro', function () {
    config(['jerelais.sms.driver' => 'mailpit', 'mail.default' => 'array']);

    app(SmsGateway::class)->send(PhoneNumber::parse('0707070707'), 'JE RELAIS : votre code est 123456.');

    $sent = Mail::mailer('array')->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('sms-2250707070707@sms.jerelais.test')
        ->and($sent[0]->getOriginalMessage()->getTextBody())->toContain('123456');
});

it('refuse la passerelle Mailpit en production', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['jerelais.sms.driver' => 'mailpit']);

    expect(fn () => app(SmsGateway::class)->send(PhoneNumber::parse('0707070707'), 'code'))->toThrow(RuntimeException::class);
});
