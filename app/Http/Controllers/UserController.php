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
        $r->validate([
            'token_hash' => 'nullable|string|max:64',
            'mode' => 'nullable|in:current,all',
        ]);

        $user = $r->user();
        $mode = $r->input('mode', 'current');
        $clientHash = strtolower(trim((string) $r->input('token_hash', '')));
        $serverHashes = $push->tokenHashesForUser($user);
        $matched = $clientHash !== '' && in_array($clientHash, $serverHashes, true);
        $traceId = (string) \Illuminate\Support\Str::uuid();

        if ($mode === 'current') {
            if ($clientHash === '') {
                $send = [
                    'success' => false,
                    'reason' => 'CURRENT_DEVICE_NOT_REGISTERED',
                    'delivery' => 'CURRENT_DEVICE_NOT_REGISTERED',
                    'subscriptions_found' => 0,
                    'subscriptions_sent' => 0,
                    'subscriptions_failed' => 0,
                    'trace_id' => $traceId,
                    'message_id' => null,
                    'token_fingerprint' => null,
                ];
            } else {
                $send = $push->sendToHash(
                    $user,
                    $clientHash,
                    'Test Notifikasi Control TB',
                    'Web Push Control TB berhasil diterima.',
                    route('dashboard'),
                    0,
                    $traceId,
                    'test_current_device',
                );
            }
        } else {
            $send = $push->sendToUser(
                $user,
                'Test Notifikasi Control TB',
                'Web Push Control TB berhasil diterima.',
                route('dashboard'),
                0,
                $traceId,
                'test_all_devices',
            );
        }

        $found = (int) ($send['subscriptions_found'] ?? 0);
        $sent = (int) ($send['subscriptions_sent'] ?? 0);
        $failed = (int) ($send['subscriptions_failed'] ?? 0);

        Log::info('[PUSH TEST]', [
            'user_id' => $user->id,
            'mode' => $mode,
            'trace_id' => $send['trace_id'] ?? $traceId,
            'subscription_count' => $found,
            'success_count' => $sent,
            'failed_count' => $failed,
            'reason' => $send['reason'] ?? null,
            'http_status' => $send['status'] ?? null,
            'fcm_project_id' => $push->projectId(),
            'message_id' => $send['message_id'] ?? null,
            'token_fingerprint' => $send['token_fingerprint'] ?? null,
            'device_match' => $matched ? 'MATCHED' : 'MISMATCHED',
        ]);

        $result = [
            'success' => (bool) ($send['success'] ?? false),
            'subscriptions_found' => $found,
            'subscriptions_sent' => $sent,
            'subscriptions_failed' => $failed,
            'reason' => $send['reason'] ?? null,
            'delivery' => $send['delivery'] ?? $send['reason'] ?? null,
            'display' => 'DISPLAY_PENDING',
            'browser_received' => $push->ackStage($send['trace_id'] ?? $traceId, 'browser_received'),
            'display_requested' => $push->ackStage($send['trace_id'] ?? $traceId, 'display_requested'),
            'fcm_project_id' => $push->projectId(),
            'credential_project_id' => $push->credentialProjectId(),
            'message_id' => $send['message_id'] ?? null,
            'token_fingerprint' => $send['token_fingerprint'] ?? null,
            'device_match' => $matched ? 'MATCHED' : 'MISMATCHED',
            'trace_id' => $send['trace_id'] ?? $traceId,
            'mode' => $mode,
        ];

        if ($r->wantsJson()) {
            return response()->json($result);
        }

        $flashKey = $result['success'] ? 'success' : 'error';
        $flashMessage = $result['success']
            ? 'FCM_ACCEPTED (trace '.$result['trace_id'].'). Bukan jaminan notification bar. Tunggu BROWSER_RECEIVED / DISPLAY_REQUESTED.'
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
