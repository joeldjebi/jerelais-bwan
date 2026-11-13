<?php

it('traduit en français chaque règle de validation de Laravel (aucune clé brute affichée)', function () {
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $french = require lang_path('fr/validation.php');

    expect(array_diff(array_keys($english), array_keys($french)))->toBe([]);
});
