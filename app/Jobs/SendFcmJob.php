<?php

namespace App\Jobs;

use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendFcmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public string $title;

    public string $body;

    public string $token;

    public string $link;

    public int $historyjadwalobatId;

    public string $traceId;

    public function __construct($title, $body, $token, $link = '', $historyjadwalobatId = 0, $traceId = '')
    {
        $this->title = (string) $title;
        $this->body = (string) $body;
        $this->token = (string) $token;
        $this->link = $link ? (string) $link : '';
        $this->historyjadwalobatId = (int) $historyjadwalobatId;
        $this->traceId = $traceId !== '' ? (string) $traceId : (string) Str::uuid();
    }

    public function handle(FcmPushService $push): void
    {
        $fingerprint = $push->fingerprint($this->token);
        Log::info('[FCM_JOB_START]', [
            'trace_id' => $this->traceId,
            'history_id' => $this->historyjadwalobatId,
            'token_fingerprint' => $fingerprint,
        ]);

        $result = $push->send(
            $this->token,
            $this->title,
            $this->body,
            $this->link,
            $this->historyjadwalobatId,
            $this->traceId,
        );

        if ($result['success']) {
            Log::info('[FCM_JOB_SUCCESS]', [
                'trace_id' => $this->traceId,
                'http_status' => $result['status'],
                'message_id' => $result['message_id'],
                'token_fingerprint' => $fingerprint,
            ]);

            return;
        }

        Log::warning('[FCM_JOB_FAILED]', [
            'trace_id' => $this->traceId,
            'reason' => $result['reason'],
            'http_status' => $result['status'],
            'token_fingerprint' => $fingerprint,
        ]);

        if ($result['reason'] === 'expired_subscription' || $result['reason'] === 'empty_token') {
            return;
        }

        throw new \RuntimeException('FCM send failed: '.$result['reason']);
    }
}
