<?php

namespace App\Http\Controllers;

use App\Models\Pasien;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Http\Request;
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
    public function register_device(Request $r, FcmPushService $push, \App\Services\PushDiagnosticLogger $diag)
    {
        $r->validate([
            'fcm_token' => 'required|string',
            'platform' => 'nullable|string|max:32',
            'device_label' => 'nullable|string|max:120',
        ]);
        $user = $r->user();
        $fingerprint = $push->fingerprint((string) $r->fcm_token);
        $diag->info('REGISTER_REQUEST', [
            'target_user_id' => $user->id,
            'token_fingerprint' => $fingerprint,
            'platform' => $r->input('platform'),
            'device_label' => $r->input('device_label'),
        ]);
        try {
            $push->rememberSubscription(
                $user,
                $r->fcm_token,
                $r->userAgent(),
                $r->input('platform'),
                $r->input('device_label'),
            );
            $diag->info('REGISTER_SAVED', [
                'target_user_id' => $user->id,
                'token_fingerprint' => $fingerprint,
                'platform' => $r->input('platform'),
                'result' => 'REGISTER_SAVED',
            ]);
        } catch (\Throwable $e) {
            $diag->exception('REGISTER_FAILED', $e, [
                'target_user_id' => $user->id,
                'token_fingerprint' => $fingerprint,
                'result' => 'REGISTER_FAILED',
            ]);
            throw $e;
        }

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
            'payload_mode' => 'nullable|in:notification,data',
        ]);

        $user = $r->user();
        $mode = $r->input('mode', 'current');
        $payloadMode = (string) $r->input('payload_mode', $mode === 'current' ? 'notification' : 'data');
        $clientHash = strtolower(trim((string) $r->input('token_hash', '')));
        $serverHashes = $push->tokenHashesForUser($user);
        $matched = $clientHash !== '' && in_array($clientHash, $serverHashes, true);
        $traceId = (string) \Illuminate\Support\Str::uuid();
        $testUrl = url('/pasien/notifikasi');
        $title = $payloadMode === 'notification'
            ? 'Control TB Push Test'
            : 'Control TB Data Test';
        $body = $payloadMode === 'notification'
            ? 'FCM notification payload test'
            : 'FCM data-only payload test';
        $extra = ['_payload_mode' => $payloadMode];

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
                    $title,
                    $body,
                    $testUrl,
                    0,
                    $traceId,
                    $payloadMode === 'notification' ? 'test_mode_a_notification' : 'test_mode_b_data',
                    $extra,
                );
            }
        } else {
            $send = $push->sendToUser(
                $user,
                $title,
                $body,
                $testUrl,
                0,
                $traceId,
                'test_all_devices',
                $extra,
            );
        }

        $found = (int) ($send['subscriptions_found'] ?? 0);
        $sent = (int) ($send['subscriptions_sent'] ?? 0);
        $failed = (int) ($send['subscriptions_failed'] ?? 0);

        app(\App\Services\PushDiagnosticLogger::class)->info('TEST_RESULT', [
            'push_trace_id' => $send['trace_id'] ?? $traceId,
            'target_user_id' => $user->id,
            'notification_type' => $mode === 'current'
                ? ($payloadMode === 'notification' ? 'test_mode_a_notification' : 'test_mode_b_data')
                : 'test_all_devices',
            'payload_mode' => $payloadMode,
            'subscription_count' => $found,
            'result' => $send['reason'] ?? null,
            'fcm_http_status' => $send['status'] ?? null,
            'fcm_message_id' => $send['message_id'] ?? null,
            'token_fingerprint' => $send['token_fingerprint'] ?? null,
            'firebase_project' => $push->projectId(),
        ]);

        $result = [
            'success' => (bool) ($send['success'] ?? false),
            'subscriptions_found' => $found,
            'subscriptions_sent' => $sent,
            'subscriptions_failed' => $failed,
            'reason' => $send['reason'] ?? null,
            'delivery' => $send['delivery'] ?? $send['reason'] ?? null,
            'display' => 'DISPLAY_PENDING',
            'raw_push_received' => $push->ackStage($send['trace_id'] ?? $traceId, 'raw_push_received'),
            'payload_parsed' => $push->ackStage($send['trace_id'] ?? $traceId, 'payload_parsed'),
            'browser_received' => $push->ackStage($send['trace_id'] ?? $traceId, 'browser_received'),
            'display_requested' => $push->ackStage($send['trace_id'] ?? $traceId, 'display_requested'),
            'payload_mode' => $payloadMode,
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
            ? 'FCM_ACCEPTED ('.$payloadMode.'). Trace: '.$result['trace_id'].'. Bukan bukti browser menerima. Hop berikutnya: RAW_PUSH_RECEIVED atau FOREGROUND_RECEIVED.'
            : 'Test Web Push gagal: '.$result['reason'].' · Trace: '.$result['trace_id'];

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
