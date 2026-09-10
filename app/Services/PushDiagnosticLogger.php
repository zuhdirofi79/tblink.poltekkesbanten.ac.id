<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class PushDiagnosticLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $stage, array $context = []): void
    {
        $this->write('debug', $stage, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $stage, array $context = []): void
    {
        $this->write('info', $stage, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $stage, array $context = []): void
    {
        $this->write('warning', $stage, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $stage, array $context = []): void
    {
        $this->write('error', $stage, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function exception(string $stage, Throwable $e, array $context = []): void
    {
        $context['exception_class'] = $e::class;
        $context['error_code'] = $context['error_code'] ?? 'exception';
        $context['message'] = $this->sanitizeMessage($e->getMessage());
        $this->error($stage, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $level, string $stage, array $context): void
    {
        $message = '[PUSH]['.$stage.']';
        $payload = $this->sanitizeContext($context);
        $payload['stage'] = $stage;
        if (! isset($payload['current_time'])) {
            $payload['current_time'] = now()->toDateTimeString();
        }

        $this->pushChannel()->{$level}($message, $payload);

        if (! $this->defaultAlreadyWritesLaravelLog()) {
            Log::{$level}($message, $payload);
        }
    }

    private function pushChannel(): LoggerInterface
    {
        return Log::channel('push');
    }

    private function defaultAlreadyWritesLaravelLog(): bool
    {
        $target = $this->normalizedPath((string) config('logging.channels.push.path', storage_path('logs/laravel.log')));
        $default = (string) config('logging.default');

        if (in_array($default, ['single', 'push'], true)) {
            return $this->normalizedPath((string) config("logging.channels.{$default}.path")) === $target;
        }

        if ($default === 'stack') {
            $channels = config('logging.channels.stack.channels', []);
            foreach ((array) $channels as $channel) {
                $channel = trim((string) $channel);
                if (! in_array($channel, ['single', 'push'], true)) {
                    continue;
                }
                if ($this->normalizedPath((string) config("logging.channels.{$channel}.path")) === $target) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizedPath(string $path): string
    {
        return str_replace('\\', '/', strtolower(rtrim($path, '/\\')));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $blocked = [
            'token', 'fcm_token', 'device_token', 'access_token', 'authorization',
            'password', 'private_key', 'private_key_id', 'client_email', 'nama',
            'name', 'phone', 'no_hp', 'patient_name', 'patient',
        ];
        $clean = [];
        foreach ($context as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, $blocked, true) || str_contains($k, 'password') || str_contains($k, 'private_key')) {
                continue;
            }
            if (is_string($value)) {
                $clean[$key] = $this->sanitizeMessage($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitizeContext($value);
            } elseif (is_object($value)) {
                $clean[$key] = $value::class;
            }
        }

        if (isset($clean['push_trace_id'])) {
            $clean['trace_id'] = $clean['push_trace_id'];
        } elseif (isset($clean['trace_id'])) {
            $clean['push_trace_id'] = $clean['trace_id'];
        }

        return $clean;
    }

    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/ya29\.[A-Za-z0-9_\-]+/', '[oauth-redacted]', $message) ?? $message;

        return substr($message, 0, 300);
    }
}
