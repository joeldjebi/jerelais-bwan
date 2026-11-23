<?php

/*
 * Notifications push de l'application (MOB-21). Aucune donnée sensible (ni motif, ni commentaire) :
 * le détail se consulte dans l'application.
 */
return [
    'relais' => [
        'received' => ['title' => 'Relais bien reçu', 'body' => 'Votre relais « :title » est arrivé. Votre coordonnateur va le vérifier.'],
        'in_review' => ['title' => 'Relais en cours de vérification', 'body' => 'Votre coordonnateur examine « :title ».'],
        'validated' => ['title' => 'Relais validé', 'body' => 'Votre relais « :title » est validé. Merci !'],
        'rejected' => ['title' => 'Relais non retenu', 'body' => 'Votre relais « :title » n’a pas été retenu. Ouvrez-le pour en savoir plus.'],
        'complement_requested' => ['title' => 'Une question sur votre relais', 'body' => 'Votre coordonnateur a une question sur « :title ».'],
        'selected' => ['title' => 'Relais retenu par la rédaction', 'body' => 'La rédaction a retenu votre relais « :title » !'],
        'broadcast' => ['title' => 'Votre relais est passé à l’antenne', 'body' => 'Votre relais « :title » est passé sur REFLET TV. Bravo !'],
    ],

    'identity' => [
        'verified' => ['title' => 'Identité vérifiée', 'body' => 'Votre identité est vérifiée : votre profil de Relais est validé.'],
        'rejected' => ['title' => 'Vérification d’identité à reprendre', 'body' => 'Votre vérification d’identité n’a pas abouti. Ouvrez l’application pour la corriger.'],
    ],

    'candidature' => [
        'accept' => ['title' => 'Candidature acceptée', 'body' => 'Bienvenue parmi les Relais ! Découvrez votre badge dans l’application.'],
        'reject' => ['title' => 'Candidature examinée', 'body' => 'Votre candidature a été examinée. Ouvrez l’application pour connaître la décision.'],
        'complement' => ['title' => 'Dossier à compléter', 'body' => 'Votre coordonnateur vous demande de compléter votre dossier.'],
    ],

    'relais_profile' => [
        'active' => ['title' => 'Compte réactivé', 'body' => 'Votre compte de Relais est de nouveau actif.'],
        'suspended' => ['title' => 'Compte suspendu', 'body' => 'Votre compte de Relais est suspendu. Contactez votre coordonnateur.'],
        'struck_off' => ['title' => 'Compte radié', 'body' => 'Votre compte de Relais a été radié, conformément à la charte.'],
    ],

    'media_rejected' => ['title' => 'Fichier refusé', 'body' => 'Un fichier de « :title » n’a pas été accepté : :reason Vous pouvez en envoyer un autre.'],

    'showcased' => ['title' => 'Votre relais est à la une', 'body' => '« :title » est publié sur « Vu sur REFLET TV ». Bravo !'],

    'transferred' => ['title' => 'Nouvelle commune', 'body' => 'Vous êtes maintenant rattaché à :commune.'],
    'transferred_sms' => 'JE RELAIS : vous êtes maintenant rattaché à la commune de :commune.',

    'gains' => [
        'earned' => ['title' => 'Nouveau gain', 'body' => '+:amount crédités sur votre portefeuille pour « :title ».'],
        'cancelled' => ['title' => 'Commission annulée', 'body' => 'La commission de :amount pour « :title » a été annulée. Ouvrez l’application pour en savoir plus.'],
    ],

    'level' => ['title' => 'Nouveau niveau : :level', 'body' => 'Bravo ! Avec :count relais validés, vous passez au niveau :level.'],

    'legal' => ['title' => 'Texte mis à jour', 'body' => '« :document » a été mis à jour. Ouvrez l’application pour le lire et l’accepter.'],

    'conversation' => ['title' => 'Nouveau message', 'body' => 'Votre coordonnateur vous a écrit.'],

    'appel' => ['title' => 'Appel à relais près de chez vous', 'body' => '« :title », jusqu’au :deadline.'],

    'identity_sms' => [
        'verified' => 'JE RELAIS : votre identité est vérifiée, votre profil de Relais est validé.',
        'rejected' => 'JE RELAIS : votre vérification d’identité est à reprendre. Ouvrez l’application pour la corriger.',
    ],

    'test' => ['title' => 'JE RELAIS', 'body' => 'Notification de test : les notifications fonctionnent.'],
];
