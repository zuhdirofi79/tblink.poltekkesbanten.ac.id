<?php

namespace App\Http\Controllers;

use App\Events\ChatReadEvent;
use App\Models\LiveChat;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LiveChatController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasPermissionTo('Admin')) {
            return redirect()->route('live-chat.admin');
        }
        $user = auth()->user();
        $pid = null;
        if ($user->hasPermissionTo('Pendamping')) {
            $pasien = Pasien::where('pendamping_id', $user->id)->first();
            if ($pasien) {
                $pid = $pasien->puskesmas?->user_id;
            }
        }
        if ($user->hasPermissionTo('Pasien')) {
            $pasien = $user->relatedPasien();
            $pid = $pasien?->puskesmas?->user_id;
        }
        if ($pid == null) {
            $admins = [];
        } else {
            $adminq = User::where('id', $pid)->get();
            $admins = $adminq->sortBy('last_seen')->values();
        }
        return inertia('live-chat/pasien', compact('admins'));
    }
    public function index_admin(Request $r)
    {
        $puskesmas = auth()->user()->puskesmas;
        $pasienq = User::whereHas('permissions', function ($q) {
            $q->where('name', 'Pasien');
        })->whereHas('pasien', function ($p) use ($puskesmas) {
            $p->where('puskesmas_id', $puskesmas->id);
        })->where('name', 'like', '%' . $r->q . '%')->with('pasien.pendamping')->orderBy('name')->get();
        $psns = $pasienq->sortByDesc('last_seen')->values();
        $sass = User::whereHas('permissions', function ($p) {
            $p->where('name', 'Superadmin');
        })->get();
        $pasiens = collect();
        foreach ($psns as $p) {
            $lc = LiveChat::where('sender_id', $p->id)->where('receiver_id', auth()->user()->id)->whereNull('read_at')->count();
            $p->count = $lc;
            $pasiens->push($p);
        }
        $sas = collect();
        foreach ($sass as $s) {
            $lcc = LiveChat::where('sender_id', $s->id)->where('receiver_id', auth()->user()->id)->whereNull('read_at')->count();
            $s->count = $lcc;
            $sas->push($s);
        }
        return inertia('live-chat/admin', compact('pasiens', 'sas'));
    }
    public function index_sa(Request $r)
    {
        $pasienq = User::whereHas('permissions', function ($q) {
            $q->where('name', 'Pasien');
        })->where('name', 'like', '%' . $r->q . '%')->with(['pasien.pendamping', 'pasien.puskesmas'])->orderBy('name')->get();
        $psns = $pasienq->sortByDesc('last_seen')->values();
        $sass = User::whereHas('permissions', function ($p) {
            $p->where('name', 'Admin');
        })->get();
        $pasiens = collect();
        foreach ($psns as $p) {
            $lc = LiveChat::where('sender_id', $p->id)->where('receiver_id', auth()->user()->id)->whereNull('read_at')->count();
            $p->count = $lc;
            $pasiens->push($p);
        }
        $sas = collect();
        foreach ($sass as $s) {
            $lcc = LiveChat::where('sender_id', $s->id)->where('receiver_id', auth()->user()->id)->whereNull('read_at')->count();
            $s->count = $lcc;
            $sas->push($s);
        }
        return inertia('live-chat/superadmin', compact('pasiens', 'sas'));
    }
    public function chat_room(User $receiver)
    {
        $usr = auth()->user();
        $msg = LiveChat::whereIn('receiver_id', [$receiver->id, $usr->id])->whereIn('sender_id', [$usr->id, $receiver->id])->orderBy('created_at', 'desc')->take(500)->get();
        $msgs = $msg->sortBy('created_at')->values();
        return inertia('live-chat/chat-room', compact('receiver', 'msgs'));
    }
    public function store(Request $r)
    {
        $receiver = User::where('id', $r->receiver_id)->first();
        $usr = auth()->user();
        $msg = new LiveChat();
        $msg->sender_id = $usr->id;
        $msg->receiver_id = $receiver->id;
        $msg->message = $r->message;
        $msg->save();
        return redirect()->back();
    }
    public function mark_read(Request $r)
    {
        $r->validate([
            'ids' => 'required|array',
            'receiver_id' => 'required',
        ]);
        $receiver = User::where('id', $r->receiver_id)->first();
        LiveChat::whereIn('id', $r->ids)->update([
            'read_at' => now(),
        ]);
        ChatReadEvent::dispatch($receiver, $r->ids);
        return redirect()->back();
    }
}
