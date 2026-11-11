<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * MTarget : envoi des SMS (codes de vérification et notifications) quand SMS_DRIVER=mtarget.
     * Le code lit toujours ces valeurs via config('services.mtarget.*'), compatible avec config:cache.
     */
    'mtarget' => [
        'url' => env('MTARGET_URL', 'https://api-public-2.mtarget.fr/messages'),
        'username' => env('MTARGET_USERNAME'),
        'password' => env('MTARGET_PASSWORD'),
        // Nom d'expéditeur alphanumérique, 11 caractères au maximum.
        'sender' => env('MTARGET_SENDER', 'JERELAIS'),
        'timeout' => (int) env('MTARGET_TIMEOUT', 30),
        'verify_ssl' => (bool) env('MTARGET_VERIFY_SSL', true),
        // Jeton du webhook des accusés de réception (POST /api/v1/sms/dlr?token=…) ; vide : webhook désactivé.
        'dlr_token' => env('MTARGET_DLR_TOKEN'),
    ],

];
