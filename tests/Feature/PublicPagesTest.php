<?php

it('affiche la page d’accueil publique [WEB-01]', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('JE RELAIS')
        ->assertSee('Devenir Relais')
        ->assertSee('Questions fréquentes');
});

it('sert les maquettes hors production', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['maquettes.console', 'maquettes.coordinateur']);
