<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Numéros de téléphone
    |--------------------------------------------------------------------------
    |
    | Le numéro est stocké chiffré ; la recherche et la détection de doublons
    | passent par une empreinte HMAC calculée avec cette clé.
    |
    */

    'phone' => [
        'hash_key' => env('PHONE_HASH_KEY') ?: env('APP_KEY'),
        'default_country_code' => '225',
    ],

    /*
    |--------------------------------------------------------------------------
    | Codes de vérification SMS (MOB-01)
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'requests_per_phone' => 3,
        'requests_per_ip' => 10,
        'requests_window_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Passerelle SMS
    |--------------------------------------------------------------------------
    |
    | « log » écrit le message dans le journal local (développement uniquement,
    | refusé en production). L'agrégateur local sera branché dès qu'il est choisi.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER', 'JE RELAIS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Antivirus (§10)
    |--------------------------------------------------------------------------
    |
    | « clamav » : clamd (conteneur clamav, profil « media » de compose.yaml).
    | « fake » : développement et tests uniquement, refusé en production.
    | Si l'antivirus ne répond pas, le fichier est refusé.
    |
    */

    'antivirus' => [
        'driver' => env('ANTIVIRUS_DRIVER', 'clamav'),
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pièces du dossier de candidature (MOB-03)
    |--------------------------------------------------------------------------
    */

    /*
    | Sauvegardes de la base (jerelais:backup, jerelais:restore-test). Les médias restent sur Wasabi :
    | versionnement du compartiment et réplication sont configurés côté Wasabi (voir docs/exploitation).
    */
    'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => 'backups',
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
        'command_prefix' => env('BACKUP_COMMAND_PREFIX', ''),
    ],

    'api' => [
        'per_user_per_minute' => (int) env('API_PER_USER_PER_MINUTE', 120),
        'anonymous_per_ip_per_minute' => (int) env('API_ANONYMOUS_PER_IP_PER_MINUTE', 1200),
    ],

    'contact_email' => env('JERELAIS_CONTACT_EMAIL', 'contact@jerelais.test'),

    'media' => [
        // Taille d'un morceau d'envoi multipart (5 Mo minimum imposé par S3, sauf le dernier).
        'part_size_mb' => 8,
        'part_url_minutes' => 30,
        'view_url_minutes' => 15,
    ],

    'candidature' => [
        'document_max_kb' => 10_240,
        'identity_url_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Démonstration (environnement local uniquement)
    |--------------------------------------------------------------------------
    |
    | Code de double authentification fixe accepté pour faciliter les démos locales.
    | Ignoré dans tout autre environnement.
    |
    */

    'demo' => [
        'two_factor_code' => env('DEMO_TWO_FACTOR_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres de la plateforme (ADM-23) : valeurs par défaut
    |--------------------------------------------------------------------------
    |
    | Modifiables par l'administrateur ; seules les clés déclarées ici existent.
    | Les clés listées dans « public_settings » sont exposées à l'application mobile.
    |
    */

    'settings' => [
        'media.video_max_seconds' => 180,
        'media.audio_max_seconds' => 600,
        'media.max_medias_per_relais' => 10,
        'media.max_file_size_mb' => 500,
        'validation.sla_urgent_minutes' => 30,
        'validation.sla_normal_minutes' => 240,
        'validation.lock_minutes' => 10,
        'candidature.quiz_min_score' => 70,
        'candidature.minimum_age' => 18,
        'candidature.minors_allowed' => false,
        'appels.local_requires_approval' => false,
        'levels.silver_validated' => 10,
        'levels.gold_validated' => 30,
        'alerts.surge_threshold' => 5,
        'alerts.surge_window_minutes' => 30,
        'retention.rejected_candidatures_days' => null,
        'retention.inactive_accounts_days' => null,
        'retention.rejected_relais_days' => null,
        'retention.validated_not_broadcast_days' => null,
        'retention.broadcast_relais_days' => null,
    ],

    'public_settings' => [
        'media.video_max_seconds',
        'media.audio_max_seconds',
        'media.max_medias_per_relais',
        'media.max_file_size_mb',
        'candidature.minimum_age',
        'candidature.minors_allowed',
        'levels.silver_validated',
        'levels.gold_validated',
    ],

];
