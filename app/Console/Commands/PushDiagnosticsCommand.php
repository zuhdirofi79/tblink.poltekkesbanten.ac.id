<?php

namespace App\Console\Commands;

use App\Services\FcmPushService;
use App\Services\PushSubscriptionStore;
use Illuminate\Console\Command;

class PushDiagnosticsCommand extends Command
{
    protected $signature = 'app:push-diagnostics {--send : Send a test log line to laravel.log (does not send FCM)}';

    protected $description = 'Report Web Push logging and FCM configuration without sending notifications';

    public function handle(FcmPushService $push, PushSubscriptionStore $store): int
    {
        $singlePath = (string) config('logging.channels.single.path');
        $dailyPath = (string) config('logging.channels.daily.path');
        $pushPath = (string) config('logging.channels.push.path');
        $credentialsPath = $push->credentialsPath();
        $logDir = dirname($singlePath);

        $subscriptionTable = 'unavailable';
        $subscriptionRows = 'unavailable';
        try {
            $subscriptionTable = $store->tableExists() ? 'yes' : 'no';
            $subscriptionRows = (string) $store->aggregatedCount();
        } catch (\Throwable $e) {
            $subscriptionTable = 'unavailable';
            $subscriptionRows = 'unavailable';
        }

        $this->table(['key', 'value'], [
            ['logging.default', (string) config('logging.default')],
            ['LOG_STACK', (string) env('LOG_STACK', '')],
            ['LOG_LEVEL', (string) config('logging.channels.single.level')],
            ['PUSH_LOG_LEVEL', (string) config('logging.channels.push.level')],
            ['single_path', $singlePath],
            ['daily_path', $dailyPath.' (rotates to laravel-YYYY-MM-DD.log)'],
            ['push_path', $pushPath],
            ['single_writable', is_dir($logDir) && is_writable($logDir) ? 'yes' : 'no'],
            ['firebase_project', $push->projectId()],
            ['credentials_readable', is_file($credentialsPath) && is_readable($credentialsPath) ? 'yes' : 'no'],
            ['queue_driver', (string) config('queue.default')],
            ['timezone', (string) config('app.timezone')],
            ['service_worker_url', rtrim((string) config('app.url'), '/').'/service-worker.js'],
            ['user_push_subscriptions_table', $subscriptionTable],
            ['subscription_rows', $subscriptionRows],
        ]);

        if ($this->option('send')) {
            app(\App\Services\PushDiagnosticLogger::class)->info('LOG_TEST', [
                'result' => 'LOG_TEST',
                'firebase_project' => $push->projectId(),
            ]);
            $this->info('Wrote [PUSH][LOG_TEST] to the push log channel (storage/logs/laravel.log).');
        }

        $this->comment('This command does not send FCM. Use Test Current Device in the UI for a real send.');

        return self::SUCCESS;
    }
}
