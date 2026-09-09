<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (Web Push)
    |--------------------------------------------------------------------------
    |
    | Existing Control TB push transport is FCM. The public VAPID key is safe
    | to expose to the browser. The private key lives in the service-account
    | JSON and must never be sent to the frontend.
    |
    */

    'project_id' => env('FCM_PROJECT_ID', 'control-tb'),

    'vapid_public_key' => env(
        'FCM_VAPID_PUBLIC_KEY',
        'BKy4MAariv5Oky8Jfm8XJdKhPNLKP4OS0PeHO4KN632D7aPls8FxRUPcHB2mc07sZDKfUqHX3kegPdthBS41jbQ'
    ),

    'credentials' => env('FCM_CREDENTIALS', storage_path('app/control-tb-service-account.json')),

];
