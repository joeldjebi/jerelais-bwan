<?php

return [
    'document_types' => [
        'cni' => 'Carte nationale d’identité (CNI)',
        'passeport' => 'Passeport',
        'permis' => 'Permis de conduire',
        'carte_consulaire' => 'Carte consulaire',
        'attestation_identite' => 'Attestation d’identité',
    ],
    'file_kinds' => [
        'recto' => 'Recto de la pièce',
        'verso' => 'Verso de la pièce',
        'page' => 'Page de la pièce',
        'selfie' => 'Selfie avec la pièce',
        'profile_photo' => 'Photo de profil',
    ],
    'statuses' => [
        'draft' => 'À compléter',
        'submitted' => 'En cours de vérification',
        'verified' => 'Identité vérifiée',
        'rejected' => 'À corriger',
    ],
    'errors' => [
        'locked' => 'Votre identité est en cours de vérification ou déjà vérifiée : elle ne peut plus être modifiée.',
        'not_relais' => 'La vérification d’identité est réservée aux Relais.',
        'file_refused' => 'Ce fichier n’est pas accepté pour « :kind » : formats acceptés : :formats.',
        'file_not_needed' => '« :kind » n’est pas demandé pour cette pièce.',
        'commune' => 'Choisissez votre commune.',
        'document_type' => 'Choisissez le type de votre pièce d’identité.',
        'file_missing' => 'Ajoutez : :kind.',
        'comment_required' => 'Indiquez au Relais ce qu’il doit corriger.',
    ],
];
