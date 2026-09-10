<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FcmPushService
{
    public function __construct(private PushSubscriptionStore $store)
    {
    }

    /**
     * Send a visible Web Push via FCM to a single device token.
     *
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string, trace_id: string}
     */
    public function send(string $token, string $title, string $body, string $url = '', int $historyJadwalObatId = 0, string $traceId = '', string $type = 'push', array $extraData = []): array
    {
        $token = trim($token);
        $fingerprint = $this->fingerprint($token);
        $traceId = $traceId !== '' ? $traceId : (string) Str::uuid();

        if ($token === '') {
            return $this->result(false, null, 'empty_token', null, $fingerprint, $traceId);
        }

        $url = $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/dashboard';
        $icon = rtrim((string) config('app.url'), '/').'/menkes192.png';
        $tag = (string) ($extraData['tag'] ?? ($historyJadwalObatId > 0
            ? 'medication-'.$historyJadwalObatId
            : 'controltb-reminder'));

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->projectId();

            if ($projectId === '') {
                Log::warning('[PUSH] FCM project id is missing', ['trace_id' => $traceId]);

                return $this->result(false, null, 'missing_project_id', null, $fingerprint, $traceId);
            }

            $data = [
                'title' => $title,
                'body' => $body,
                'link' => $url,
                'url' => $url,
                'icon' => $icon,
                'badge' => $icon,
                'tag' => $tag,
                'type' => $type,
                'history_id' => (string) $historyJadwalObatId,
                'push_trace_id' => $traceId,
                'ack_received' => $this->ackUrl($traceId, 'browser_received'),
                'ack_displayed' => $this->ackUrl($traceId, 'display_requested'),
            ];
            foreach ($extraData as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $data[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
            }

            $payload = [
                'message' => [
                    'token' => $token,
                    'data' => $data,
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
                Log::info('[FCM_ACCEPTED]', [
                    'trace_id' => $traceId,
                    'project' => $projectId,
                    'token_fingerprint' => $fingerprint,
                    'message_id' => $messageId,
                    'http_status' => $status,
                    'message_type' => 'data',
                    'type' => $type,
                ]);

                return $this->result(true, $status, 'FCM_ACCEPTED', is_string($messageId) ? $messageId : null, $fingerprint, $traceId);
            }

            if ($this->isExpiredSubscription($status, $bodyText)) {
                $this->forgetToken($token);
                Log::warning('[PUSH] Expired subscription removed', [
                    'http_status' => $status,
                    'reason' => 'expired_or_unregistered',
                    'token_fingerprint' => $fingerprint,
                    'trace_id' => $traceId,
                ]);

                return $this->result(false, $status, 'expired_subscription', null, $fingerprint, $traceId);
            }

            $reason = $this->safeErrorReason($bodyText);
            Log::warning('[PUSH] FCM send failed', [
                'http_status' => $status,
                'reason' => $reason,
                'token_fingerprint' => $fingerprint,
                'project' => $projectId,
                'trace_id' => $traceId,
            ]);

            return $this->result(false, $status, $reason, null, $fingerprint, $traceId);
        } catch (\Throwable $e) {
            Log::error('[PUSH] FCM send exception', [
                'message' => $e->getMessage(),
                'token_fingerprint' => $fingerprint,
                'trace_id' => $traceId,
            ]);

            return $this->result(false, null, 'exception', null, $fingerprint, $traceId);
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

        return $this->store->tokensFor($user);
    }

    public function tokenHashesForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $this->store->hashesFor($user);
    }

    public function fingerprintsForUser(?User $user): array
    {
        return array_map(fn (string $hash) => substr($hash, 0, 12), $this->tokenHashesForUser($user));
    }

    /**
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string, subscriptions_found: int, subscriptions_sent: int, subscriptions_failed: int, trace_id: string}
     */
    public function sendToUser(?User $user, string $title, string $body, string $url = '', int $historyJadwalObatId = 0, string $traceId = '', string $type = 'push', array $extraData = []): array
    {
        $traceId = $traceId !== '' ? $traceId : (string) Str::uuid();
        $tokens = $this->tokensForUser($user);
        $sent = 0;
        $failed = 0;
        $last = $this->result(false, null, 'empty_token', null, '', $traceId);

        foreach ($tokens as $token) {
            $last = $this->send($token, $title, $body, $url, $historyJadwalObatId, $traceId, $type, $extraData);
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
            $this->markHistoryNotified($historyJadwalObatId, $user?->id);
        }

        $last['subscriptions_found'] = count($tokens);
        $last['subscriptions_sent'] = $sent;
        $last['subscriptions_failed'] = $failed;
        $last['trace_id'] = $traceId;

        return $last;
    }

    public function sendToHash(User $user, string $tokenHash, string $title, string $body, string $url = '', int $historyJadwalObatId = 0, string $traceId = '', string $type = 'push'): array
    {
        $traceId = $traceId !== '' ? $traceId : (string) Str::uuid();
        $token = $this->store->tokenForHash($user, $tokenHash);
        if (! $token) {
            $empty = $this->result(false, null, 'CURRENT_DEVICE_NOT_REGISTERED', null, '', $traceId);
            $empty['subscriptions_found'] = 0;
            $empty['subscriptions_sent'] = 0;
            $empty['subscriptions_failed'] = 0;

            return $empty;
        }

        $send = $this->send($token, $title, $body, $url, $historyJadwalObatId, $traceId, $type);
        if ($send['success']) {
            $this->markHistoryNotified($historyJadwalObatId, $user->id);
        }
        $send['subscriptions_found'] = 1;
        $send['subscriptions_sent'] = $send['success'] ? 1 : 0;
        $send['subscriptions_failed'] = $send['success'] ? 0 : 1;

        return $send;
    }

    public function rememberSubscription(User $user, string $token, ?string $userAgent = null, ?string $platform = null, ?string $deviceLabel = null): void
    {
        $this->store->upsert($user, $token, $userAgent, $platform, $deviceLabel);
    }

    public function forgetCurrentDevice(User $user, ?string $token = null): void
    {
        $this->store->forget($user, $token);
    }

    public function ackStage(string $traceId, string $stage): ?string
    {
        $value = Cache::get('push_ack:'.$traceId.':'.$stage);

        return is_string($value) ? $value : null;
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
     * @return array{success: bool, status: int|null, reason: string, message_id: ?string, token_fingerprint: string, delivery: string, trace_id: string}
     */
    private function result(bool $success, ?int $status, string $reason, ?string $messageId, string $fingerprint, string $traceId = ''): array
    {
        return [
            'success' => $success,
            'status' => $status,
            'reason' => $reason,
            'message_id' => $messageId,
            'token_fingerprint' => $fingerprint,
            'delivery' => $success ? 'FCM_ACCEPTED' : $reason,
            'trace_id' => $traceId,
        ];
    }

    private function ackUrl(string $traceId, string $stage): string
    {
        $exp = now()->addHour()->timestamp;
        $sig = hash_hmac('sha256', $traceId.'|'.$stage.'|'.$exp, (string) config('app.key'));

        return url('/push/ack').'?'.http_build_query([
            't' => $traceId,
            's' => $stage,
            'e' => $exp,
            'sig' => $sig,
        ]);
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
        $this->store->forgetToken($token);
    }

    private function markHistoryNotified(int $historyJadwalObatId, ?int $userId = null): void
    {
        if ($historyJadwalObatId <= 0 || ! $userId) {
            return;
        }

        $history = HistoryJadwalObatPasien::find($historyJadwalObatId);
        if (! $history) {
            return;
        }

        $sent = is_array($history->notifikasi_dikirim) ? $history->notifikasi_dikirim : [];
        foreach ($sent as $entry) {
            if (is_array($entry) && (int) ($entry['user_id'] ?? 0) === $userId) {
                return;
            }
        }

        $sent[] = [
            'user_id' => $userId,
            'at' => now()->toIso8601String(),
        ];
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
