<?php

use App\Domain\Auth\Data\PhoneNumber;

it('normalise les saisies courantes au format E.164 [MOB-01]', function (string $input) {
    expect(PhoneNumber::parse($input)->e164)->toBe('+2250707070707');
})->with([
    '07 07 07 07 07',
    '0707070707',
    '+225 07 07 07 07 07',
    '00225 0707070707',
    '+225-07-07-07-07-07',
]);

it('accepte un numéro étranger de la diaspora', function () {
    expect(PhoneNumber::parse('+33 6 12 34 56 78')->e164)->toBe('+33612345678');
});

it('refuse les numéros invalides', function (string $input) {
    expect(PhoneNumber::tryParse($input))->toBeNull();
})->with([
    '12345',
    '+225 07 07 07',
    'pas un numéro',
    '707070707',
]);

it('produit une empreinte stable sans exposer le numéro', function () {
    $hash = PhoneNumber::parse('07 07 07 07 07')->hash();

    expect($hash)->toBe(PhoneNumber::parse('+2250707070707')->hash())
        ->and($hash)->toHaveLength(64)
        ->and($hash)->not->toContain('0707070707');
});

it('masque le numéro pour l’affichage', function () {
    expect(PhoneNumber::parse('0707070742')->masked())->toBe('+22507 •• •• •• 42');
});
