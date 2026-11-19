<?php

return [
    'audiences' => [
        'all' => 'Tous les Relais actifs',
        'regions' => 'Relais de certaines régions',
        'communes' => 'Relais de certaines communes',
        'relais' => 'Un Relais précis',
        'identity_pending' => 'Relais à l’identité non vérifiée',
        'payout_missing' => 'Relais sans coordonnées de paiement',
    ],
    'statuses' => [
        'scheduled' => 'Programmée',
        'sending' => 'En cours d’envoi',
        'sent' => 'Envoyée',
        'cancelled' => 'Annulée',
    ],
    'screens' => [
        'home' => 'Accueil',
        'announcements' => 'Annonces',
        'appels' => 'Appels à relais',
        'relais' => 'Mes relais',
        'gains' => 'Mes gains',
        'identity' => 'Vérification d’identité',
        'profile' => 'Mon profil',
    ],
    'errors' => [
        'no_recipient' => 'Aucun Relais ne correspond à ce ciblage.',
        'not_cancellable' => 'Seule une annonce programmée peut être annulée.',
        'zone_required' => 'Choisissez au moins une région ou une commune.',
        'relais_required' => 'Choisissez le Relais destinataire.',
        'scheduled_in_past' => 'Choisissez une date à venir.',
    ],
];
