<?php

return [
    'kinds' => [
        'validated' => 'Relais validé',
        'broadcast' => 'Relais diffusé',
    ],
    'statuses' => [
        'due' => 'Crédité',
        'paid' => 'Crédité',
        'cancelled' => 'Annulé',
    ],
    'payout_statuses' => [
        'requested' => 'En attente',
        'paid' => 'Payé',
        'rejected' => 'Refusé',
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
        'identity_not_verified_relais' => 'Faites vérifier votre identité pour retirer vos gains.',
        'payout_account_missing' => 'Enregistrez d’abord vos coordonnées Mobile Money.',
        'withdrawal_pending' => 'Un retrait est déjà en cours : attendez qu’il soit traité.',
        'balance_below_minimum' => 'Votre solde n’atteint pas le minimum de retrait (:min).',
        'amount_below_minimum' => 'Le retrait minimum est de :min.',
        'amount_above_balance' => 'Montant supérieur à votre solde disponible (:available).',
        'withdrawal_not_pending' => 'Cette demande de retrait a déjà été traitée.',
        'already_withdrawn' => 'Ce gain a déjà été retiré ou est en cours de retrait : il ne peut plus être annulé.',
        'phone_invalid' => 'Numéro Mobile Money invalide : indiquez un numéro ivoirien, par exemple 0707070707 ou +2250707070707.',
        'method_unknown' => 'Opérateur inconnu : wave, orange_money, mtn_momo ou moov_money.',
    ],
];
