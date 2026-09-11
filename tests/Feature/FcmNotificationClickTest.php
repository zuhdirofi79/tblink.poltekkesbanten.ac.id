<?php

use App\Services\FcmPushService;
use Illuminate\Support\Facades\URL;

function productionAppUrl(): void
{
    $root = 'https://tblink.poltekkesbanten.ac.id';
    config(['app.url' => $root]);
    URL::forceRootUrl($root);
    URL::forceScheme('https');
}

test('medication reminder fcm payload uses consistent dashboard click urls', function () {
    productionAppUrl();

    $payload = app(FcmPushService::class)->buildMessagePayload(
        'test-token',
        'Pengingat Minum Obat KORTIKOSTEROID',
        'Obat KORTIKOSTEROID jam 10:16 belum diminum',
        route('dashboard'),
        42,
        'trace-click',
        'medication_reminder',
    );

    $expected = 'https://tblink.poltekkesbanten.ac.id/dashboard';
    $link = $payload['message']['webpush']['fcm_options']['link'] ?? null;
    $dataUrl = $payload['message']['data']['url'] ?? null;

    expect($link)->toBe($expected)
        ->and($dataUrl)->toBe($expected)
        ->and($link)->toBe($dataUrl)
        ->and($payload['message']['data']['type'])->toBe('medication_reminder')
        ->and($payload['message']['data']['history_id'])->toBe('42')
        ->and($payload['message']['notification']['title'])->toStartWith('Pengingat Minum Obat');
});

test('empty or relative click url canonicalizes to production dashboard', function () {
    productionAppUrl();
    $push = app(FcmPushService::class);

    expect($push->resolveClickUrl(''))->toBe('https://tblink.poltekkesbanten.ac.id/dashboard')
        ->and($push->resolveClickUrl('/dashboard'))->toBe('https://tblink.poltekkesbanten.ac.id/dashboard')
        ->and($push->defaultDashboardUrl())->toBe('https://tblink.poltekkesbanten.ac.id/dashboard');
});

test('external notification urls cannot open-redirect away from dashboard', function () {
    productionAppUrl();
    $push = app(FcmPushService::class);
    $expected = 'https://tblink.poltekkesbanten.ac.id/dashboard';

    expect($push->resolveClickUrl('https://evil.example/phish'))->toBe($expected)
        ->and($push->resolveClickUrl('https://example.com'))->toBe($expected);

    $payload = $push->buildMessagePayload(
        'test-token',
        'Pengingat Minum Obat',
        'body',
        'https://evil.example/phish',
        1,
        'trace-redirect',
        'medication_reminder',
    );

    expect($payload['message']['data']['url'])->toBe($expected)
        ->and($payload['message']['webpush']['fcm_options']['link'])->toBe($expected);
});

test('service worker click handler stays same-origin and defaults to dashboard', function () {
    $sw = (string) file_get_contents(public_path('firebase-messaging-sw.js'));

    expect($sw)->toContain("self.addEventListener('notificationclick'")
        ->and($sw)->toContain('stopImmediatePropagation')
        ->and($sw)->toContain("const DEFAULT_PATH = '/dashboard'")
        ->and($sw)->toContain('resolved.origin !== self.location.origin')
        ->and($sw)->toContain('clients.openWindow')
        ->and($sw)->toContain('client.navigate')
        ->and($sw)->toContain('FCM_MSG')
        ->and($sw)->not->toContain('/pasien/notifikasi');
});
