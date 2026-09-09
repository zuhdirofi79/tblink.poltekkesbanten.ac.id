<?php

namespace App\Http\Controllers;

use App\Models\Pasien;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Illuminate\Notifications\DatabaseNotification as Notification;

class UserController extends Controller
{
    public function index(Request $r)
    {
        $userq = User::with(['permissions']);
        if ($r->q) {
            $userq->where(function ($f) use ($r) {
                $f->where('name', 'like', '%' . $r->q . '%')
                    ->orWhere('username', 'like', '%' . $r->q . '%');
            });
        }
        $users = $userq->paginate(10);
        return inertia('user-management/index', compact('users'));
    }
    public function edit(User $user)
    {
        $permissions = Permission::all();
        $user->perms = $user->getPermissionNames();
        return inertia('user-management/um-edit', compact('user', 'permissions'));
    }
    public function create()
    {
        $permissions = Permission::all();
        return inertia('user-management/um-create', compact('permissions'));
    }
    public function update(Request $r, User $user)
    {
        $r->validate([
            'name' => 'required',
            'password' => 'nullable|confirmed|min:6',
            'email' => 'email|nullable',
            'perms' => 'nullable|array',
        ]);
        $user->update([
            'name' => $r->name,
            'email' => $r->email,
        ]);
        if ($r->password) {
            $user->password = bcrypt($r->password);
        }
        $user->save();
        if ($r->perms) {
            $user->syncPermissions($r->perms);
        }
        return redirect()->back()->with('success', 'User ' . $user->name . ' berhasil dirubah!');
    }
    public function store(Request $r)
    {
        $r->validate([
            'name' => 'required',
            'username' => 'required|unique:users,username',
            'password' => 'required|min:6|confirmed',
            'permissions' => 'array',
            'email' => 'email|nullable',
        ]);
        $user = User::create([
            'name' => $r->name,
            'username' => $r->username,
            'password' => bcrypt($r->password),
            'email' => $r->email,
        ]);
        $user->syncPermissions($r->permissions);
        if ($user->can('Admin')) {
            $user->puskesmas()->create(['nama_puskesmas' => $user->name]);
        }
        return redirect(route('users.index'))->with('success', 'User ' . $user->name . ' berhasil ditambahkan');
    }
    public function destroy(User $user)
    {
        $pus = $user->puskesmas;
        if (Pasien::where('puskesmas_id', $pus->id)->count()) {
            return redirect()->back()->with('error', 'Puskesmas ' . $pus->nama_puskesmas . ' masih memiliki data pasien');
        } else {
            $user->delete();
            return redirect()->back()->with('success', 'User ' . $user->name . ' berhasil dihapus');
        }
    }
    #region notification
    public function notification_index()
    {
        $user = auth()->user();
        $unreadNotifications = $user->unreadNotifications;
        $readNotifications = $user->readNotifications()->orderBy('created_at', 'desc')->skip(0)->take(50)->get();
        return inertia('notifications', compact('unreadNotifications', 'readNotifications'));
    }
    public function read_notification(Notification $notification)
    {
        $notification->markAsRead();
        // NotifReadEvent::dispatch(auth()->user()->id);
        return redirect()->back();
    }
    public function count_notifications()
    {
        $user = auth()->user();
        return $user->unreadNotifications()->count();
    }
    public function register_device(Request $r, FcmPushService $push)
    {
        $r->validate([
            'fcm_token' => 'required|string',
            'platform' => 'nullable|string|max:32',
            'device_label' => 'nullable|string|max:120',
        ]);
        $user = $r->user();
        $push->rememberSubscription(
            $user,
            $r->fcm_token,
            $r->userAgent(),
            $r->input('platform'),
            $r->input('device_label'),
        );

        return redirect()->back()->with('success', 'Registrasi notifikasi berhasil! Subscription tersimpan.');
    }

    public function unregister_device(Request $r, FcmPushService $push)
    {
        $r->validate([
            'fcm_token' => 'nullable|string',
        ]);
        $user = $r->user();
        $push->forgetCurrentDevice($user, $r->input('fcm_token'));

        return redirect()->back()->with('success', 'Langganan notifikasi dihentikan.');
    }

    public function send_test_notif(Request $r, FcmPushService $push)
    {
        $user = $r->user();
        $clientHash = strtolower(trim((string) $r->input('token_hash', '')));
        $serverHash = $push->tokenHash($user->device_token);
        $matched = $clientHash !== '' && $serverHash !== null && hash_equals($serverHash, $clientHash);

        $send = $push->sendToUser(
            $user,
            'Test Notifikasi Control TB',
            'Web Push Control TB berhasil diterima.',
            route('dashboard'),
        );

        $found = (int) ($send['subscriptions_found'] ?? 0);
        $sent = (int) ($send['subscriptions_sent'] ?? 0);
        $failed = (int) ($send['subscriptions_failed'] ?? 0);

        if ($found === 0) {
            $send['reason'] = 'No active web push subscription for user '.$user->id;
            Log::warning('[PUSH TEST] '.$send['reason'], ['user_id' => $user->id, 'subscription_count' => 0]);
        } else {
            Log::info('[PUSH TEST]', [
                'user_id' => $user->id,
                'subscription_count' => $found,
                'success_count' => $sent,
                'failed_count' => $failed,
                'reason' => $send['reason'],
                'http_status' => $send['status'],
                'fcm_project_id' => $push->projectId(),
                'credential_project_id' => $push->credentialProjectId(),
                'message_id' => $send['message_id'] ?? null,
                'token_fingerprint' => $send['token_fingerprint'] ?? null,
                'device_match' => $matched ? 'MATCHED' : 'MISMATCHED',
            ]);
        }

        $result = [
            'success' => (bool) $send['success'],
            'subscriptions_found' => $found,
            'subscriptions_sent' => $sent,
            'subscriptions_failed' => $failed,
            'reason' => $send['reason'],
            'delivery' => $send['delivery'] ?? $send['reason'],
            'display' => 'DISPLAY_PENDING',
            'fcm_project_id' => $push->projectId(),
            'credential_project_id' => $push->credentialProjectId(),
            'message_id' => $send['message_id'] ?? null,
            'token_fingerprint' => $send['token_fingerprint'] ?? null,
            'device_match' => $matched ? 'MATCHED' : 'MISMATCHED',
        ];

        if ($r->wantsJson()) {
            return response()->json($result);
        }

        $flashKey = $result['success'] ? 'success' : 'error';
        $flashMessage = $result['success']
            ? 'FCM_ACCEPTED. Bukan jaminan notifikasi sudah tampil. Cek notification bar (foreground: onMessage, background: service worker).'
            : 'Test Web Push gagal: '.$result['reason'];

        return redirect()->back()->with($flashKey, $flashMessage)->with('push_test', $result);
    }

    public function notifikasi_admin(Request $r)
    {
        $user = auth()->user();
        $notifs = $user->unreadNotifications()->orderBy('created_at', 'desc')->paginate(10);
        return inertia('notifikasi/admin', compact('notifs'));
    }
    #endregion
}
