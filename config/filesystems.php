<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Médias des relais (photos, vidéos, audio) : Wasabi, privé, accès par URL signées.
         */
        'medias' => [
            'driver' => 's3',
            'key' => env('WAS_ACCESS_KEY'),
            'secret' => env('WAS_SECRET_KEY'),
            'region' => env('WASABI_REGION'),
            'bucket' => env('WASABI_BUCKET'),
            'url' => env('WASABI_URL'),
            'endpoint' => env('WASABI_ENDPOINT'),
            'root' => 'medias',
            'visibility' => 'private',
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => true,
        ],

        /*
         * Pièces d'identité, selfies, autorisations parentales : séparés des médias (§9.4).
         * Bucket dédié recommandé en production (WASABI_IDENTITY_BUCKET), sinon même bucket sous un préfixe distinct.
         */
        'identity' => [
            'driver' => 's3',
            'key' => env('WAS_ACCESS_KEY'),
            'secret' => env('WAS_SECRET_KEY'),
            'region' => env('WASABI_REGION'),
            'bucket' => env('WASABI_IDENTITY_BUCKET') ?: env('WASABI_BUCKET'),
            'endpoint' => env('WASABI_ENDPOINT'),
            'root' => 'identity',
            'visibility' => 'private',
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
