<?php

return [
    'targets' => [
        'national' => 'Tout le réseau',
        'communes' => 'Communes',
        'regions' => 'Régions',
        'radius' => 'Rayon autour d’un point',
    ],
    'statuses' => [
        'pending_approval' => 'En attente d’accord',
        'open' => 'Ouvert',
        'closed' => 'Clos',
    ],
    'sms' => 'JE RELAIS : appel à relais « :title » près de chez vous, jusqu’au :deadline. Ouvrez l’application pour répondre.',
    'errors' => [
        'forbidden' => 'Vous ne pouvez pas lancer d’appel à relais.',
        'no_commune' => 'Choisissez au moins une commune.',
        'not_targeted' => 'Cet appel ne concerne pas votre zone ou il est clos.',
    ],
];
