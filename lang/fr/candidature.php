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

    'age_refused' => 'Il faut avoir au moins :age ans pour devenir Relais.',
    'locked' => 'Votre dossier a été déposé : il n’est plus modifiable pour le moment.',
    'incomplete' => 'Votre dossier n’est pas encore complet.',
    'step_incomplete' => 'Complétez l’étape « :step ».',
    'motivation_too_short' => 'Votre motivation doit faire au moins :min caractères.',

];
