<?php

return [
    'states' => [
        'uploading' => 'Envoi en cours',
        'received' => 'Reçu, en attente de validation',
        'in_review' => 'En cours de vérification',
        'complement_requested' => 'Complément demandé',
        'validated' => 'Validé',
        'rejected' => 'Rejeté',
        'sensitive' => 'Sensible, arbitrage de la rédaction',
        'selected' => 'Sélectionné par la rédaction',
        'broadcast' => 'Diffusé',
        'archived' => 'Archivé',
    ],
    'urgencies' => [
        'normal' => 'Normal',
        'important' => 'Important',
        'urgent' => 'Urgent',
    ],
    'consents' => [
        'yes' => 'Oui, les personnes filmées sont d’accord',
        'no' => 'Non',
        'not_applicable' => 'Personne n’est reconnaissable',
    ],
    'credits' => [
        'named' => 'Crédité sous mon nom',
        'pseudonym' => 'Crédité sous un pseudonyme',
        'anonymous' => 'Rester anonyme',
    ],
    'attributes' => [
        'category' => 'catégorie',
        'title' => 'titre',
        'description' => 'description',
        'urgency' => 'niveau d’urgence',
        'has_minors' => 'présence de mineurs',
        'people_consent' => 'accord des personnes filmées',
        'credit_mode' => 'crédit à l’antenne',
        'credit_name' => 'pseudonyme',
        'declared_commune' => 'commune',
    ],
    'errors' => [
        'not_relais' => 'Seul un Relais actif peut envoyer un relais.',
        'client_uuid_taken' => 'Cet identifiant est déjà utilisé.',
        'not_editable' => 'Ce relais a déjà été envoyé : il n’est plus modifiable.',
        'no_commune' => 'Indiquez la commune du lieu : la position GPS ne permet pas de la déduire.',
        'missing' => 'Il manque des informations avant l’envoi.',
    ],
];
