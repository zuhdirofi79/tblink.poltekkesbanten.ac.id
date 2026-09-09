<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (Web Push)
    |--------------------------------------------------------------------------
    |
    | Web client keys are public (same values as VITE_FIREBASE_*).
    | The service-account JSON stays backend-only.
    |
    */

    'project_id' => env('FCM_PROJECT_ID', env('VITE_FIREBASE_PROJECT_ID', 'controltb-3262b')),

    'vapid_public_key' => env(
        'VITE_FIREBASE_VAPID_KEY',
        env('FCM_VAPID_PUBLIC_KEY', 'BJXrALxEpfP20pM8cZN8jIvK4hiRB4xySMyR98P0v5NLav--Ag5Z2gNq65F2xdjoIg_TyPGdP5n317mVHIuTcPc')
    ),

    'credentials' => env(
        'FCM_CREDENTIALS',
        env('GOOGLE_APPLICATION_CREDENTIALS', storage_path('app/private/control-tb-service-account.json'))
    ),

    'web' => [
        'apiKey' => env('VITE_FIREBASE_API_KEY', 'AIzaSyDBeJWMNi-4DR2x15MOjBJ2dY6CrOcCMe4'),
        'authDomain' => env('VITE_FIREBASE_AUTH_DOMAIN', 'controltb-3262b.firebaseapp.com'),
        'projectId' => env('VITE_FIREBASE_PROJECT_ID', env('FCM_PROJECT_ID', 'controltb-3262b')),
        'storageBucket' => env('VITE_FIREBASE_STORAGE_BUCKET', 'controltb-3262b.firebasestorage.app'),
        'messagingSenderId' => env('VITE_FIREBASE_MESSAGING_SENDER_ID', '211769927271'),
        'appId' => env('VITE_FIREBASE_APP_ID', '1:211769927271:web:73d2c9f018248320ee16c2'),
    ],

];
