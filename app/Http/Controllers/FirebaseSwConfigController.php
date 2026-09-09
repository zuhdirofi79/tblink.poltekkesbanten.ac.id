<?php

namespace App\Http\Controllers;

class FirebaseSwConfigController extends Controller
{
    public function __invoke()
    {
        $web = config('fcm.web', []);
        $payload = [
            'apiKey' => (string) ($web['apiKey'] ?? ''),
            'authDomain' => (string) ($web['authDomain'] ?? ''),
            'projectId' => (string) ($web['projectId'] ?? ''),
            'storageBucket' => (string) ($web['storageBucket'] ?? ''),
            'messagingSenderId' => (string) ($web['messagingSenderId'] ?? ''),
            'appId' => (string) ($web['appId'] ?? ''),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return response('self.__FIREBASE_WEB_CONFIG__='.$json.';', 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
