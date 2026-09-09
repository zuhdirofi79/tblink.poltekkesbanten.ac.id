<?php

namespace App\Jobs;

use App\Services\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFcmJob implements ShouldQueue
{
    use Queueable;

    public string $title;

    public string $body;

    public string $token;

    public string $link;

    public int $historyjadwalobatId;

    public function __construct($title, $body, $token, $link = '', $historyjadwalobatId = 0)
    {
        $this->title = (string) $title;
        $this->body = (string) $body;
        $this->token = (string) $token;
        $this->link = $link ? (string) $link : '';
        $this->historyjadwalobatId = (int) $historyjadwalobatId;
    }

    public function handle(FcmPushService $push): void
    {
        $push->send(
            $this->token,
            $this->title,
            $this->body,
            $this->link,
            $this->historyjadwalobatId,
        );
    }
}
