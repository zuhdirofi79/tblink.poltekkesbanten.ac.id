<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $u = $request->user();
        if (!auth()->guest()) {
            Cache::put($u->id . '-online', now());
        }
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'push_test' => fn() => $request->session()->get('push_test'),
            ],
            'auth' => [
                'user' => $u,
            ],
            'push' => (function () use ($u) {
                $push = app(\App\Services\FcmPushService::class);
                $hashes = $u ? $push->tokenHashesForUser($u) : [];

                return [
                    'vapidPublicKey' => config('fcm.vapid_public_key'),
                    'firebaseProjectId' => config('fcm.project_id'),
                    'messagingSenderId' => config('fcm.web.messagingSenderId'),
                    'serverRegistered' => $hashes !== [],
                    'serverTokenHashes' => $hashes,
                    'serverFingerprints' => array_map(fn (string $hash) => substr($hash, 0, 12), $hashes),
                    'multiDeviceReady' => true,
                    'multiDeviceTable' => Cache::remember('schema_user_push_subscriptions', 300, function () {
                        try {
                            return \Illuminate\Support\Facades\Schema::hasTable('user_push_subscriptions');
                        } catch (\Throwable $e) {
                            return false;
                        }
                    }),
                ];
            })(),
        ];
    }
}
