<?php

namespace App\Models;

use App\Events\ChatNotifEvent;
use Illuminate\Database\Eloquent\Model;

class LiveChat extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'read_at',
    ];
    protected $appends = [
        'role',
    ];
    public function getRoleAttribute()
    {
        if ($this->sender->hasPermissionTo('Admin')) {
            return 'Admin';
        } else if ($this->sender->hasPermissionTo('Pendamping')) {
            return 'Pendamping';
        } else {
            return 'Pasien';
        }
    }
    protected $dates = [
        'read_at',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
    protected static function booted()
    {
        static::created(function ($chat) {
            event(new ChatNotifEvent($chat->receiver, $chat->sender, $chat));
        });
    }
}
