<?php

use Illuminate\Database\Eloquent\Model;

arch('aucune fonction de debug ne part en production')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('règles de sécurité PHP (OWASP)')
    ->preset()
    ->security();

arch('env() est réservé aux fichiers de configuration')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('le métier ne dépend pas de la couche HTTP')
    ->expect('App\Domain')
    ->not->toUse(['App\Http', 'App\Livewire']);

arch('les modèles étendent Eloquent')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('les contrôleurs sont suffixés')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');
