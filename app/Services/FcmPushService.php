<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\User;
use App\Models\UserPushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FcmPushService
{
    /**
     * Send a visible Web Push via FCM to a single device token.
     *
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string}
     */
    public function send(string $token, string $title, string $body, string $url = '', int $historyJadwalObatId = 0): array
    {
        $token = trim($token);
        $fingerprint = $this->fingerprint($token);

        if ($token === '') {
            return $this->result(false, null, 'empty_token', null, $fingerprint);
        }

        $url = $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/dashboard';
        $icon = rtrim((string) config('app.url'), '/').'/menkes192.png';
        $tag = $historyJadwalObatId > 0
            ? 'controltb-obat-'.$historyJadwalObatId
            : 'controltb-reminder';

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->projectId();

            if ($projectId === '') {
                Log::warning('[PUSH] FCM project id is missing');

                return $this->result(false, null, 'missing_project_id', null, $fingerprint);
            }

            // Data-only: Chrome will not auto-display. SW/onMessage must call showNotification.
            $payload = [
                'message' => [
                    'token' => $token,
                    'data' => [
                        'title' => $title,
                        'body' => $body,
                        'link' => $url,
                        'url' => $url,
                        'icon' => $icon,
                        'badge' => $icon,
                        'tag' => $tag,
                    ],
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high',
                        ],
                        'fcm_options' => [
                            'link' => $url,
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            $status = $response->status();
            $bodyText = $response->body();
            $decoded = $response->json();
            $messageId = is_array($decoded) ? ($decoded['name'] ?? null) : null;

            if ($response->successful()) {
                $this->markHistoryNotified($historyJadwalObatId);
                Log::info('[PUSH] FCM_ACCEPTED', [
                    'project' => $projectId,
                    'token_fingerprint' => $fingerprint,
                    'message_id' => $messageId,
                    'http_status' => $status,
                    'message_type' => 'data',
                ]);

                return $this->result(true, $status, 'FCM_ACCEPTED', is_string($messageId) ? $messageId : null, $fingerprint);
            }

            if ($this->isExpiredSubscription($status, $bodyText)) {
                $this->forgetToken($token);
                Log::warning('[PUSH] Expired subscription removed', [
                    'user_ids' => User::where('device_token', $token)->pluck('id')->all(),
                    'http_status' => $status,
                    'reason' => 'expired_or_unregistered',
                    'token_fingerprint' => $fingerprint,
                ]);

                return $this->result(false, $status, 'expired_subscription', null, $fingerprint);
            }

            $reason = $this->safeErrorReason($bodyText);
            Log::warning('[PUSH] FCM send failed', [
                'http_status' => $status,
                'reason' => $reason,
                'token_fingerprint' => $fingerprint,
                'project' => $projectId,
            ]);

            return $this->result(false, $status, $reason, null, $fingerprint);
        } catch (\Throwable $e) {
            Log::error('[PUSH] FCM send exception', [
                'message' => $e->getMessage(),
                'token_fingerprint' => $fingerprint,
            ]);

            return $this->result(false, null, 'exception', null, $fingerprint);
        }
    }

    /**
     * @return list<string>
     */
    public function tokensForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $tokens = [];

        if (Schema::hasTable('user_push_subscriptions')) {
            $tokens = UserPushSubscription::query()
                ->where('user_id', $user->id)
                ->pluck('token')
                ->all();
        }

        $legacy = trim((string) $user->device_token);
        if ($legacy !== '' && ! in_array($legacy, $tokens, true)) {
            $tokens[] = $legacy;
        }

        return array_values(array_unique(array_filter(array_map('trim', $tokens))));
    }

    /**
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string, subscriptions_found: int, subscriptions_sent: int, subscriptions_failed: int}
     */
    public function sendToUser(?User $user, string $title, string $body, string $url = '', int $historyJadwalObatId = 0): array
    {
        $tokens = $this->tokensForUser($user);
        $sent = 0;
        $failed = 0;
        $last = $this->result(false, null, 'empty_token', null, '');

        foreach ($tokens as $token) {
            $last = $this->send($token, $title, $body, $url, $historyJadwalObatId);
            if ($last['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($tokens === []) {
            $last['reason'] = 'empty_token';
        } elseif ($sent > 0) {
            $last['success'] = true;
            $last['reason'] = 'FCM_ACCEPTED';
            $last['delivery'] = 'FCM_ACCEPTED';
        }

        $last['subscriptions_found'] = count($tokens);
        $last['subscriptions_sent'] = $sent;
        $last['subscriptions_failed'] = $failed;

        return $last;
    }

    public function rememberSubscription(User $user, string $token, ?string $userAgent = null, ?string $platform = null, ?string $deviceLabel = null): void
    {
        $token = trim($token);
        $user->forceFill(['device_token' => $token])->save();

        if ($token === '' || ! Schema::hasTable('user_push_subscriptions')) {
            return;
        }

        UserPushSubscription::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'user_id' => $user->id,
                'token' => $token,
                'device_label' => $deviceLabel,
                'user_agent' => $userAgent,
                'platform' => $platform,
                'last_seen_at' => now(),
            ],
        );
    }

    public function forgetCurrentDevice(User $user, ?string $token = null): void
    {
        $token = trim((string) $token);

        if ($token !== '' && Schema::hasTable('user_push_subscriptions')) {
            UserPushSubscription::query()
                ->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $token))
                ->delete();
        }

        $legacy = trim((string) $user->device_token);
        $legacyMatches = $token === '' || ($legacy !== '' && hash_equals($legacy, $token));

        if (! $legacyMatches) {
            return;
        }

        $replacement = null;
        if (Schema::hasTable('user_push_subscriptions')) {
            $replacement = UserPushSubscription::query()
                ->where('user_id', $user->id)
                ->value('token');
        }

        $user->forceFill(['device_token' => $replacement])->save();
    }

    public function tokenHash(?string $token): ?string
    {
        $token = trim((string) $token);

        return $token === '' ? null : hash('sha256', $token);
    }

    public function fingerprint(string $token): string
    {
        $hash = $this->tokenHash($token);

        return $hash ? substr($hash, 0, 12) : '';
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

    /**
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string}
     */
    private function result(bool $success, ?int $status, string $reason, ?string $messageId, string $fingerprint): array
    {
        return [
            'success' => $success,
            'status' => $status,
            'reason' => $reason,
            'message_id' => $messageId,
            'token_fingerprint' => $fingerprint,
            'delivery' => $success ? 'FCM_ACCEPTED' : $reason,
        ];
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

        if (Schema::hasTable('user_push_subscriptions')) {
            UserPushSubscription::query()
                ->where('token_hash', hash('sha256', $token))
                ->delete();
        }
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
