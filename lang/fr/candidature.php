<?php

return [

    'statuses' => [
        'draft' => 'En cours de rédaction',
        'submitted' => 'Reçue',
        'under_review' => 'En cours d’examen',
        'on_hold' => 'En attente',
        'complement_requested' => 'Complément demandé',
        'accepted' => 'Acceptée',
        'rejected' => 'Refusée',
    ],

    'steps' => [
        'identite' => 'Identité',
        'territoire' => 'Commune et quartier',
        'profil' => 'Profil et motivation',
        'documents' => 'Pièces justificatives',
        'charte' => 'Charte et conditions',
        'formation' => 'Formation et quiz',
    ],

    'documents' => [
        'profile_photo' => ['label' => 'Photo de profil', 'hint' => 'Visage bien éclairé, de face. Elle figurera sur votre badge.'],
        'id_front' => ['label' => 'Pièce d’identité · recto', 'hint' => 'CNI, passeport ou attestation d’identité. Tout le document doit être lisible.'],
        'id_back' => ['label' => 'Pièce d’identité · verso', 'hint' => 'Le dos du même document.'],
        'selfie' => ['label' => 'Selfie de contrôle', 'hint' => 'Prenez-vous en photo en tenant votre pièce d’identité à côté du visage.'],
        'parental_authorization' => ['label' => 'Autorisation parentale', 'hint' => 'Signée par un parent ou tuteur légal (photo ou PDF).'],
    ],

    'genders' => [
        'femme' => 'Femme',
        'homme' => 'Homme',
        'non_precise' => 'Je préfère ne pas le préciser',
    ],

    'occupations' => [
        'eleve' => 'Élève',
        'etudiant' => 'Étudiant·e',
        'commercant' => 'Commerçant·e',
        'salarie' => 'Salarié·e',
        'independant' => 'Indépendant·e',
        'artisan' => 'Artisan',
        'agriculteur' => 'Agriculteur·rice',
        'sans_emploi' => 'Sans emploi',
        'autre' => 'Autre',
    ],

    'languages' => [
        'fr' => 'Français',
        'dioula' => 'Dioula',
        'baoule' => 'Baoulé',
        'bete' => 'Bété',
        'senoufo' => 'Sénoufo',
        'agni' => 'Agni',
        'gouro' => 'Gouro',
        'yacouba' => 'Yacouba',
        'attie' => 'Attié',
        'en' => 'Anglais',
        'autre' => 'Autre langue',
    ],

    'attributes' => [
        'last_name' => 'nom',
        'first_names' => 'prénoms',
        'birth_date' => 'date de naissance',
        'gender' => 'genre',
        'email' => 'adresse e-mail',
        'commune' => 'commune',
        'localite' => 'quartier ou village',
        'lieu_type' => 'lieu type',
        'occupation' => 'profession ou statut',
        'languages' => 'langues parlées',
        'interests' => 'centres d’intérêt',
        'motivation' => 'motivation',
    ],

    'document_not_expected' => 'Cette pièce n’est pas demandée pour votre dossier.',
    'decision' => [
        'reason_required' => 'Choisissez le motif du refus dans la liste.',
        'comment_required' => 'Précisez au candidat ce qu’il doit compléter.',
    ],

    'sms' => [
        'accept' => 'JE RELAIS : bonne nouvelle, votre candidature est acceptée ! Ouvrez l’application pour découvrir votre badge.',
        'reject' => 'JE RELAIS : votre candidature a été examinée. Connectez-vous pour connaître la décision.',
        'complement' => 'JE RELAIS : votre coordonnateur vous demande de compléter votre dossier. Connectez-vous pour le faire.',
    ],

    'duplicates' => [
        'document' => 'Même pièce d’identité',
        'identity' => 'Même nom et date de naissance',
        'email' => 'Même adresse e-mail',
        'device' => 'Même appareil',
    ],

    'age_refused' => 'Il faut avoir au moins :age ans pour devenir Relais.',
    'locked' => 'Votre dossier a été déposé : il n’est plus modifiable pour le moment.',
    'incomplete' => 'Votre dossier n’est pas encore complet.',
    'step_incomplete' => 'Complétez l’étape « :step ».',
    'motivation_too_short' => 'Votre motivation doit faire au moins :min caractères.',

];
