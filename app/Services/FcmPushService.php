<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    /**
     * Send a visible Web Push via FCM to a single device token.
     *
     * @return array{success: bool, status: int|null, reason: string}
     */
    public function send(string $token, string $title, string $body, string $url = '', int $historyJadwalObatId = 0): array
    {
        $token = trim($token);

        if ($token === '') {
            return [
                'success' => false,
                'status' => null,
                'reason' => 'empty_token',
            ];
        }

        $url = $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/dashboard';
        $icon = rtrim((string) config('app.url'), '/').'/menkes192.png';

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->projectId();

            if ($projectId === '') {
                Log::warning('[PUSH] FCM project id is missing');

                return [
                    'success' => false,
                    'status' => null,
                    'reason' => 'missing_project_id',
                ];
            }

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high',
                        ],
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                            'icon' => $icon,
                            'badge' => $icon,
                            'tag' => $historyJadwalObatId > 0
                                ? 'controltb-obat-'.$historyJadwalObatId
                                : 'controltb-reminder',
                            'renotify' => true,
                        ],
                        'fcm_options' => [
                            'link' => $url,
                        ],
                    ],
                    'data' => [
                        'title' => $title,
                        'body' => $body,
                        'link' => $url,
                        'url' => $url,
                        'icon' => $icon,
                        'tag' => $historyJadwalObatId > 0
                            ? 'controltb-obat-'.$historyJadwalObatId
                            : 'controltb-reminder',
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            $status = $response->status();
            $bodyText = $response->body();

            if ($response->successful()) {
                $this->markHistoryNotified($historyJadwalObatId);

                return [
                    'success' => true,
                    'status' => $status,
                    'reason' => 'sent',
                ];
            }

            if ($this->isExpiredSubscription($status, $bodyText)) {
                $this->forgetToken($token);
                Log::warning('[PUSH] Expired subscription removed', [
                    'user_ids' => User::where('device_token', $token)->pluck('id')->all(),
                    'http_status' => $status,
                    'reason' => 'expired_or_unregistered',
                ]);

                return [
                    'success' => false,
                    'status' => $status,
                    'reason' => 'expired_subscription',
                ];
            }

            Log::warning('[PUSH] FCM send failed', [
                'http_status' => $status,
                'reason' => $this->safeErrorReason($bodyText),
            ]);

            return [
                'success' => false,
                'status' => $status,
                'reason' => $this->safeErrorReason($bodyText),
            ];
        } catch (\Throwable $e) {
            Log::error('[PUSH] FCM send exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => null,
                'reason' => 'exception',
            ];
        }
    }

    public function projectId(): string
    {
        $fromConfig = trim((string) config('fcm.project_id'));
        $fromFile = $this->credentialProjectId();
        $stale = ['', 'c-tb', 'control-tb'];

        if (! in_array($fromConfig, $stale, true)) {
            if ($fromFile !== '' && $fromFile !== $fromConfig) {
                Log::warning('[PUSH] FCM_PROJECT_ID differs from service account project_id', [
                    'fcm_project_id' => $fromConfig,
                    'credential_project_id' => $fromFile,
                ]);
            }

            return $fromConfig;
        }

        return $fromFile !== '' ? $fromFile : 'controltb-3262b';
    }

    public function credentialProjectId(): string
    {
        return trim((string) ($this->credentialPayload()['project_id'] ?? ''));
    }

    public function credentialsPath(): string
    {
        $configured = (string) config('fcm.credentials');

        $candidates = array_filter([
            $configured,
            storage_path('app/control-tb-service-account.json'),
            storage_path('app/private/control-tb-service-account.json'),
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $configured;
    }

    public function credentialsExist(): bool
    {
        return is_file($this->credentialsPath());
    }

    private function accessToken(): string
    {
        $cacheKey = 'fcm_access_token_'.$this->projectId();
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $path = $this->credentialsPath();
        if (! is_file($path)) {
            throw new \RuntimeException('FCM service account file is missing');
        }

        $client = new \Google\Client();
        $client->setAuthConfig($path);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $token = $client->getAccessToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Unable to obtain FCM access token');
        }

        $expires = (int) ($token['expires_in'] ?? 3600);
        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, $expires - 100)));

        return $accessToken;
    }

    private function credentialPayload(): array
    {
        $path = $this->credentialsPath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isExpiredSubscription(int $status, string $body): bool
    {
        if (in_array($status, [404, 410], true)) {
            return true;
        }

        return str_contains($body, 'UNREGISTERED')
            || str_contains($body, 'NOT_FOUND');
    }

    private function forgetToken(string $token): void
    {
        User::where('device_token', $token)->update(['device_token' => null]);
    }

    private function markHistoryNotified(int $historyJadwalObatId): void
    {
        if ($historyJadwalObatId <= 0) {
            return;
        }

        $history = HistoryJadwalObatPasien::find($historyJadwalObatId);
        if (! $history) {
            return;
        }

        $sent = is_array($history->notifikasi_dikirim) ? $history->notifikasi_dikirim : [];
        $sent[] = now()->isoFormat('DD/MM/YYYY HH:mm');
        $history->notifikasi_dikirim = $sent;
        $history->save();
    }

    private function safeErrorReason(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $status = $decoded['error']['status'] ?? null;
            $code = $decoded['error']['code'] ?? null;
            if ($status || $code) {
                return trim(($code ? $code.' ' : '').(string) $status);
            }
        }

        return 'fcm_error';
    }
}
