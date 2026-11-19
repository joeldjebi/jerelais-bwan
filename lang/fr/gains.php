<?php

return [
    'kinds' => [
        'validated' => 'Relais validé',
        'broadcast' => 'Relais diffusé',
    ],
    'statuses' => [
        'due' => 'À payer',
        'paid' => 'Payé',
        'cancelled' => 'Annulé',
    ],
    'methods' => [
        'wave' => 'Wave',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'cash' => 'Espèces',
        'other' => 'Autre',
    ],
    'cancelled_by_rejection' => 'Relais finalement rejeté par la rédaction.',
    'errors' => [
        'not_due' => 'Cette commission n’est plus à payer.',
        'identity_not_verified' => 'Identité du Relais non vérifiée : vérifiez-la avant de payer.',
        'not_paid' => 'Cette commission n’a pas été payée.',
    ],
];
