<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPushSubscription;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-device FCM tokens without requiring a production migration.
 *
 * Authoritative order:
 * 1. user_push_subscriptions table when it exists
 * 2. storage/app/private/user_push_subscriptions.json otherwise
 * 3. users.device_token as legacy fallback
 */
class PushSubscriptionStore
{
    public function tableExists(): bool
    {
        try {
            return Schema::hasTable('user_push_subscriptions');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array{token: string, token_hash: string, device_label: ?string, platform: ?string}>
     */
    public function subscriptionsFor(User $user): array
    {
        if ($this->tableExists()) {
            return UserPushSubscription::query()
                ->where('user_id', $user->id)
                ->get(['token', 'token_hash', 'device_label', 'platform'])
                ->map(fn (UserPushSubscription $row) => [
                    'token' => (string) $row->token,
                    'token_hash' => (string) $row->token_hash,
                    'device_label' => $row->device_label,
                    'platform' => $row->platform,
                ])
                ->all();
        }

        return $this->fileSubscriptions()[(string) $user->id] ?? [];
    }

    /**
     * @return list<string>
     */
    public function tokensFor(User $user): array
    {
        $tokens = array_map(
            fn (array $row) => trim((string) ($row['token'] ?? '')),
            $this->subscriptionsFor($user)
        );

        $legacy = trim((string) $user->device_token);
        if ($legacy !== '' && ! in_array($legacy, $tokens, true)) {
            $tokens[] = $legacy;
        }

        return array_values(array_unique(array_filter($tokens)));
    }

    public function upsert(User $user, string $token, ?string $userAgent = null, ?string $platform = null, ?string $deviceLabel = null): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        $hash = hash('sha256', $token);

        if ($this->tableExists()) {
            UserPushSubscription::query()->updateOrCreate(
                ['token_hash' => $hash],
                [
                    'user_id' => $user->id,
                    'token' => $token,
                    'device_label' => $deviceLabel,
                    'user_agent' => $userAgent,
                    'platform' => $platform,
                    'last_seen_at' => now(),
                ],
            );
        } else {
            $all = $this->fileSubscriptions();
            $uid = (string) $user->id;
            $rows = $all[$uid] ?? [];
            $found = false;
            foreach ($rows as $i => $row) {
                if (($row['token_hash'] ?? '') === $hash) {
                    $rows[$i] = array_merge($row, [
                        'token' => $token,
                        'token_hash' => $hash,
                        'device_label' => $deviceLabel,
                        'user_agent' => $userAgent,
                        'platform' => $platform,
                        'last_seen_at' => now()->toIso8601String(),
                    ]);
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $rows[] = [
                    'token' => $token,
                    'token_hash' => $hash,
                    'device_label' => $deviceLabel,
                    'user_agent' => $userAgent,
                    'platform' => $platform,
                    'last_seen_at' => now()->toIso8601String(),
                ];
            }
            $all[$uid] = $rows;
            $this->writeFileSubscriptions($all);
        }

        $user->forceFill(['device_token' => $token])->save();
    }

    public function forget(User $user, ?string $token = null): void
    {
        $token = trim((string) $token);
        $hash = $token !== '' ? hash('sha256', $token) : null;

        if ($this->tableExists()) {
            $query = UserPushSubscription::query()->where('user_id', $user->id);
            if ($hash) {
                $query->where('token_hash', $hash);
            }
            $query->delete();
        } else {
            $all = $this->fileSubscriptions();
            $uid = (string) $user->id;
            if (isset($all[$uid])) {
                if ($hash) {
                    $all[$uid] = array_values(array_filter(
                        $all[$uid],
                        fn (array $row) => ($row['token_hash'] ?? '') !== $hash
                    ));
                } else {
                    unset($all[$uid]);
                }
                $this->writeFileSubscriptions($all);
            }
        }

        $remaining = $this->subscriptionsFor($user);
        $legacy = trim((string) $user->device_token);
        $legacyMatches = $token === '' || ($legacy !== '' && hash_equals($legacy, $token));
        if ($legacyMatches) {
            $user->forceFill(['device_token' => $remaining[0]['token'] ?? null])->save();
        }
    }

    public function forgetToken(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }
        $hash = hash('sha256', $token);

        if ($this->tableExists()) {
            UserPushSubscription::query()->where('token_hash', $hash)->delete();
        } else {
            $all = $this->fileSubscriptions();
            foreach ($all as $uid => $rows) {
                $all[$uid] = array_values(array_filter(
                    $rows,
                    fn (array $row) => ($row['token_hash'] ?? '') !== $hash
                ));
            }
            $this->writeFileSubscriptions($all);
        }

        User::where('device_token', $token)->update(['device_token' => null]);
    }

    public function tokenForHash(User $user, string $hash): ?string
    {
        $hash = strtolower(trim($hash));
        if ($hash === '') {
            return null;
        }

        foreach ($this->subscriptionsFor($user) as $row) {
            if (hash_equals(strtolower((string) ($row['token_hash'] ?? '')), $hash)) {
                return (string) $row['token'];
            }
        }

        $legacy = trim((string) $user->device_token);
        if ($legacy !== '' && hash_equals(hash('sha256', $legacy), $hash)) {
            return $legacy;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function hashesFor(User $user): array
    {
        $hashes = [];
        foreach ($this->tokensFor($user) as $token) {
            $hashes[] = hash('sha256', $token);
        }

        return array_values(array_unique($hashes));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function fileSubscriptions(): array
    {
        $path = $this->filePath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function writeFileSubscriptions(array $data): void
    {
        $path = $this->filePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return;
        }

        try {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_SLASHES));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function filePath(): string
    {
        return storage_path('app/private/user_push_subscriptions.json');
    }
}
