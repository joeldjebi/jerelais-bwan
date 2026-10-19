<?php

return [
    'groups' => [
        'media' => ['Médias', 'Durées et tailles maximales acceptées à l’envoi (MOB-11, §10).'],
        'validation' => ['Validation', 'Délais cibles de traitement et verrouillage des relais (COO-17, §8.2).'],
        'alerts' => ['Alertes', 'Afflux inhabituel de relais sur une même commune, signe d’un événement (ADM-03).'],
        'candidature' => ['Candidatures', 'Conditions d’admission des Relais (MOB-05, §9.3).'],
        'retention' => ['Conservation des données', 'Durées avant purge automatique (§9.4). Vide : aucune purge.'],
    ],

    'labels' => [
        'media' => [
            'video_max_seconds' => 'Durée maximale d’une vidéo',
            'audio_max_seconds' => 'Durée maximale d’un audio',
            'max_medias_per_relais' => 'Médias par relais',
            'max_file_size_mb' => 'Taille maximale d’un fichier',
        ],
        'validation' => [
            'sla_urgent_minutes' => 'Délai cible — relais urgent',
            'sla_normal_minutes' => 'Délai cible — relais normal',
            'lock_minutes' => 'Verrouillage d’un relais ouvert',
        ],
        'alerts' => [
            'surge_threshold' => 'Seuil d’afflux',
            'surge_window_minutes' => 'Fenêtre d’observation',
        ],
        'candidature' => [
            'quiz_min_score' => 'Score minimal au quiz',
            'minimum_age' => 'Âge minimum',
            'minors_allowed' => 'Admettre des candidats mineurs',
        ],
        'retention' => [
            'rejected_candidatures_days' => 'Candidatures refusées',
            'inactive_accounts_days' => 'Comptes inactifs',
            'rejected_relais_days' => 'Relais rejetés',
            'validated_not_broadcast_days' => 'Relais validés non diffusés',
            'broadcast_relais_days' => 'Relais diffusés',
        ],
    ],

    'units' => [
        'media' => [
            'video_max_seconds' => 's',
            'audio_max_seconds' => 's',
            'max_medias_per_relais' => 'médias',
            'max_file_size_mb' => 'Mo',
        ],
        'validation' => [
            'sla_urgent_minutes' => 'min',
            'sla_normal_minutes' => 'min',
            'lock_minutes' => 'min',
        ],
        'alerts' => [
            'surge_threshold' => 'relais',
            'surge_window_minutes' => 'min',
        ],
        'candidature' => [
            'quiz_min_score' => '%',
            'minimum_age' => 'ans',
        ],
        'retention' => [
            'rejected_candidatures_days' => 'jours',
            'inactive_accounts_days' => 'jours',
            'rejected_relais_days' => 'jours',
            'validated_not_broadcast_days' => 'jours',
            'broadcast_relais_days' => 'jours',
        ],
    ],
];
