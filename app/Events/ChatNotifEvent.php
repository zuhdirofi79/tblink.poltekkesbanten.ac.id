<?php

namespace App\Events;

use App\Models\LiveChat;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatNotifEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $title;
    public $body;
    protected $user;
    public $chat;
    public $sender_id;
    public function __construct(User $user, User $sender, LiveChat $chat)
    {
        $this->user = $user;
        $this->title = 'Chat dari ' . $sender->name;
        $this->body = \Str::limit($chat->message, 50, '...');
        $this->sender_id = $sender->id;
        $this->chat = $chat;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat-notification.' . $this->user->id),
        ];
    }
}
