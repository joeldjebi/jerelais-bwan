<?php

return [
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
        'file_not_needed_for' => '« :kind » n’est pas demandé pour « :type » : envoyez seulement les faces indiquées par sides.',
        'document_type_first' => 'Choisissez d’abord le type de pièce (document_type) pour envoyer ses faces.',
        'document_type_unknown' => 'Choisissez une pièce de la liste GET /auth/register/options : :values.',
        'infected' => '« :kind » a été refusé par l’antivirus : envoyez un autre fichier.',
        'last_name' => 'Indiquez votre nom.',
        'first_names' => 'Indiquez vos prénoms.',
        'gender' => 'Indiquez votre sexe.',
        'birth_date' => 'Indiquez votre date de naissance.',
        'commune' => 'Choisissez votre commune.',
        'document_type' => 'Choisissez le type de votre pièce d’identité.',
        'file_missing' => 'Ajoutez : :kind.',
        'comment_required' => 'Indiquez au Relais ce qu’il doit corriger.',
    ],
];
