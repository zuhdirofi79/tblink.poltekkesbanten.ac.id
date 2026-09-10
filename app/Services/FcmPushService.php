<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FcmPushService
{
    public function __construct(
        private PushSubscriptionStore $store,
        private PushDiagnosticLogger $diag,
    ) {
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
            $this->diag->warning('NO_SUBSCRIPTION', [
                'push_trace_id' => $traceId,
                'result' => 'empty_token',
                'error_code' => 'empty_token',
                'notification_type' => $type,
            ]);

            return $this->result(false, null, 'empty_token', null, $fingerprint, $traceId);
        }

        $url = $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/dashboard';
        $targetUserId = isset($extraData['_target_user_id']) ? (int) $extraData['_target_user_id'] : null;
        $deviceSource = (string) ($extraData['_source'] ?? '');
        $deviceLabel = (string) ($extraData['_device_label'] ?? '');
        $platform = (string) ($extraData['_platform'] ?? '');
        $lastSeenAt = (string) ($extraData['_last_seen_at'] ?? '');
        foreach (array_keys($extraData) as $key) {
            if (str_starts_with((string) $key, '_')) {
                unset($extraData[$key]);
            }
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->projectId();

            if ($projectId === '') {
                $this->diag->warning('FCM_REJECTED', [
                    'push_trace_id' => $traceId,
                    'result' => 'missing_project_id',
                    'error_code' => 'missing_project_id',
                    'token_fingerprint' => $fingerprint,
                    'target_user_id' => $targetUserId,
                    'notification_type' => $type,
                    'history_id' => $historyJadwalObatId ?: null,
                ]);

                return $this->result(false, null, 'missing_project_id', null, $fingerprint, $traceId);
            }

            $data = [
                'type' => $type,
                'url' => $url,
                'push_trace_id' => $traceId,
            ];
            if ($historyJadwalObatId > 0) {
                $data['history_id'] = (string) $historyJadwalObatId;
            }
            foreach ($extraData as $key => $value) {
                $data[(string) $key] = $value;
            }
            $data = $this->stringifyDataMap($data);

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high',
                            'TTL' => '300',
                        ],
                        'fcm_options' => [
                            'link' => $url,
                        ],
                    ],
                ],
            ];

            $this->diag->info('FCM_SEND_START', [
                'push_trace_id' => $traceId,
                'firebase_project' => $projectId,
                'target_user_id' => $targetUserId,
                'token_fingerprint' => $fingerprint,
                'notification_type' => $type,
                'history_id' => $historyJadwalObatId ?: ($extraData['visit_id'] ?? null),
                'source' => $deviceSource ?: null,
                'platform' => $platform ?: null,
                'device_label' => $deviceLabel ?: null,
                'last_seen_at' => $lastSeenAt ?: null,
            ]);

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            $status = $response->status();
            $bodyText = $response->body();
            $decoded = $response->json();
            $messageId = is_array($decoded) ? ($decoded['name'] ?? null) : null;
            $firebase = $this->firebaseError($status, $bodyText);

            if ($response->successful()) {
                $this->diag->info('FCM_ACCEPTED', [
                    'push_trace_id' => $traceId,
                    'firebase_project' => $projectId,
                    'target_user_id' => $targetUserId,
                    'token_fingerprint' => $fingerprint,
                    'notification_type' => $type,
                    'fcm_http_status' => $status,
                    'fcm_message_id' => $messageId,
                    'result' => 'FCM_ACCEPTED',
                    'history_id' => $historyJadwalObatId ?: ($extraData['visit_id'] ?? null),
                ]);

                return $this->result(true, $status, 'FCM_ACCEPTED', is_string($messageId) ? $messageId : null, $fingerprint, $traceId);
            }

            if ($this->isProjectMismatch($firebase['error_code'], $bodyText)) {
                $this->diag->error('TOKEN_PROJECT_MISMATCH', [
                    'push_trace_id' => $traceId,
                    'firebase_project' => $projectId,
                    'target_user_id' => $targetUserId,
                    'token_fingerprint' => $fingerprint,
                    'notification_type' => $type,
                    'fcm_http_status' => $status,
                    'error_code' => $firebase['error_code'],
                    'result' => 'TOKEN_PROJECT_MISMATCH',
                ]);
            }

            if ($this->isExpiredSubscription($status, $bodyText)) {
                $this->forgetToken($token);
                $this->diag->warning('TOKEN_INVALID', [
                    'push_trace_id' => $traceId,
                    'firebase_project' => $projectId,
                    'target_user_id' => $targetUserId,
                    'token_fingerprint' => $fingerprint,
                    'notification_type' => $type,
                    'fcm_http_status' => $status,
                    'error_code' => $firebase['error_code'] ?: 'UNREGISTERED',
                    'result' => 'TOKEN_INVALID',
                ]);
                $this->diag->warning('TOKEN_REMOVED', [
                    'push_trace_id' => $traceId,
                    'token_fingerprint' => $fingerprint,
                    'target_user_id' => $targetUserId,
                    'result' => 'TOKEN_REMOVED',
                ]);

                return $this->result(false, $status, 'expired_subscription', null, $fingerprint, $traceId);
            }

            $this->diag->warning('FCM_REJECTED', [
                'push_trace_id' => $traceId,
                'firebase_project' => $projectId,
                'target_user_id' => $targetUserId,
                'token_fingerprint' => $fingerprint,
                'notification_type' => $type,
                'fcm_http_status' => $status,
                'error_code' => $firebase['error_code'],
                'result' => 'FCM_REJECTED',
                'message' => $firebase['message'],
            ]);

            return $this->result(false, $status, $firebase['error_code'] ?: 'fcm_error', null, $fingerprint, $traceId);
        } catch (\Throwable $e) {
            $this->diag->exception('FCM_EXCEPTION', $e, [
                'push_trace_id' => $traceId,
                'target_user_id' => $targetUserId,
                'token_fingerprint' => $fingerprint,
                'notification_type' => $type,
                'result' => 'FCM_EXCEPTION',
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

        $this->diag->info('SUBSCRIPTIONS_FOUND', [
            'push_trace_id' => $traceId,
            'target_user_id' => $user?->id,
            'subscription_count' => count($tokens),
            'notification_type' => $type,
            'history_id' => $historyJadwalObatId ?: ($extraData['visit_id'] ?? null),
        ]);

        if ($tokens === []) {
            $this->diag->warning('NO_SUBSCRIPTION', [
                'push_trace_id' => $traceId,
                'target_user_id' => $user?->id,
                'subscription_count' => 0,
                'notification_type' => $type,
                'history_id' => $historyJadwalObatId ?: ($extraData['visit_id'] ?? null),
                'result' => 'NO_SUBSCRIPTION',
            ]);
            $last['reason'] = 'empty_token';
            $last['subscriptions_found'] = 0;
            $last['subscriptions_sent'] = 0;
            $last['subscriptions_failed'] = 0;
            $last['trace_id'] = $traceId;

            return $last;
        }

        foreach ($tokens as $token) {
            $meta = $user ? $this->store->metadataForToken($user, $token) : [];
            $this->diag->info('DEVICE_TARGET', [
                'push_trace_id' => $traceId,
                'target_user_id' => $user?->id,
                'token_fingerprint' => $this->fingerprint($token),
                'platform' => $meta['platform'] ?? null,
                'device_label' => $meta['device_label'] ?? null,
                'last_seen_at' => $meta['last_seen_at'] ?? null,
                'source' => $meta['source'] ?? null,
                'notification_type' => $type,
            ]);
            $last = $this->send($token, $title, $body, $url, $historyJadwalObatId, $traceId, $type, array_merge($extraData, [
                '_target_user_id' => $user?->id,
                '_source' => $meta['source'] ?? null,
                '_platform' => $meta['platform'] ?? null,
                '_device_label' => $meta['device_label'] ?? null,
                '_last_seen_at' => $meta['last_seen_at'] ?? null,
            ]));
            if ($last['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent > 0) {
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

    public function sendToHash(User $user, string $tokenHash, string $title, string $body, string $url = '', int $historyJadwalObatId = 0, string $traceId = '', string $type = 'push', array $extraData = []): array
    {
        $traceId = $traceId !== '' ? $traceId : (string) Str::uuid();
        $token = $this->store->tokenForHash($user, $tokenHash);
        if (! $token) {
            $this->diag->warning('NO_SUBSCRIPTION', [
                'push_trace_id' => $traceId,
                'target_user_id' => $user->id,
                'subscription_count' => 0,
                'notification_type' => $type,
                'result' => 'CURRENT_DEVICE_NOT_REGISTERED',
                'token_fingerprint' => substr($tokenHash, 0, 12),
            ]);
            $empty = $this->result(false, null, 'CURRENT_DEVICE_NOT_REGISTERED', null, '', $traceId);
            $empty['subscriptions_found'] = 0;
            $empty['subscriptions_sent'] = 0;
            $empty['subscriptions_failed'] = 0;

            return $empty;
        }

        $meta = $this->store->metadataForToken($user, $token);
        $this->diag->info('DEVICE_TARGET', [
            'push_trace_id' => $traceId,
            'target_user_id' => $user->id,
            'token_fingerprint' => $this->fingerprint($token),
            'platform' => $meta['platform'] ?? null,
            'device_label' => $meta['device_label'] ?? null,
            'last_seen_at' => $meta['last_seen_at'] ?? null,
            'source' => $meta['source'] ?? null,
            'notification_type' => $type,
        ]);
        $send = $this->send($token, $title, $body, $url, $historyJadwalObatId, $traceId, $type, array_merge($extraData, [
            '_target_user_id' => $user->id,
            '_source' => $meta['source'] ?? null,
            '_platform' => $meta['platform'] ?? null,
            '_device_label' => $meta['device_label'] ?? null,
            '_last_seen_at' => $meta['last_seen_at'] ?? null,
        ]));
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
                $this->diag->warning('TOKEN_PROJECT_MISMATCH', [
                    'firebase_project' => $fromConfig,
                    'credential_project_id' => $fromFile,
                    'result' => 'config_credential_mismatch',
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

    /**
     * HTTP v1 FCM data map values must be strings.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyDataMap(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $out[(string) $key] = $value ? '1' : '0';

                continue;
            }
            if (is_scalar($value)) {
                $out[(string) $key] = (string) $value;

                continue;
            }
            $encoded = json_encode($value);
            if (is_string($encoded) && $encoded !== '') {
                $out[(string) $key] = $encoded;
            }
        }

        return $out;
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

    private function firebaseError(int $status, string $body): array
    {
        $decoded = json_decode($body, true);
        $statusName = is_array($decoded) ? ($decoded['error']['status'] ?? null) : null;
        $code = is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
        $message = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
        $errorCode = trim(($status ?: '').' '.($statusName ?: ''));
        if (is_string($message) && str_contains($message, 'SENDER_ID_MISMATCH')) {
            $errorCode = trim($errorCode.' SENDER_ID_MISMATCH');
        }
        if ($statusName) {
            $errorCode = trim(($code ? $code.' ' : '').$statusName);
        }

        return [
            'error_code' => $errorCode !== '' ? $errorCode : (string) $status,
            'message' => is_string($message) ? substr($message, 0, 200) : null,
        ];
    }

    private function isProjectMismatch(?string $errorCode, string $body): bool
    {
        $haystack = strtoupper(($errorCode ?? '').' '.$body);

        return str_contains($haystack, 'SENDER_ID_MISMATCH')
            || str_contains($haystack, 'SENDERIDMISMATCH');
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
