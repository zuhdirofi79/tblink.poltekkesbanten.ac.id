<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('test-private-channel', function ($user) {
    return ($user ? true : false);
}, ['guards' => 'web']);
Broadcast::channel('presence-users', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->hasPermissionTo('Admin') ? 'Admin' : ($user->hasPermissionTo('Pendamping') ? 'Pendamping' : 'Pasien')
    ];
});
Broadcast::channel('chat-notification.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('inchat-notif.{id}.{receiver_id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
