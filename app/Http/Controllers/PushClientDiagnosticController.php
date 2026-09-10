<?php

namespace App\Http\Controllers;

use App\Services\PushDiagnosticLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushClientDiagnosticController extends Controller
{
    private const EVENTS = [
        'SERVICE_WORKER_REGISTER_FAILED',
        'SERVICE_WORKER_NOT_READY',
        'SERVICE_WORKER_READY_FAILED',
        'FCM_GET_TOKEN_FAILED',
        'GET_TOKEN_FAILED',
        'FCM_TOKEN_EMPTY',
        'PUSH_PERMISSION_DENIED',
        'PUSH_SUBSCRIPTION_FAILED',
        'FCM_ONMESSAGE_ERROR',
        'ONMESSAGE_ATTACHED',
        'ONMESSAGE_RECEIVED',
        'ONMESSAGE_SETUP_FAILED',
        'SHOW_NOTIFICATION_FAILED',
        'FOREGROUND_RECEIVED',
        'DISPLAY_REQUESTED',
        'DISPLAY_FAILED',
        'REGISTER_CLIENT_ERROR',
        'HARD_REREGISTER_FAILED',
        'HARD_REREGISTER_OK',
    ];

    public function __invoke(Request $request, PushDiagnosticLogger $diag)
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'in:'.implode(',', self::EVENTS)],
            'push_trace_id' => ['nullable', 'string', 'max:64'],
            'token_fingerprint' => ['nullable', 'string', 'max:16'],
            'message' => ['nullable', 'string', 'max:200'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        $event = $validated['event'];
        $stage = match ($event) {
            'FOREGROUND_RECEIVED', 'ONMESSAGE_RECEIVED' => 'FOREGROUND_RECEIVED',
            'ONMESSAGE_ATTACHED' => 'ONMESSAGE_ATTACHED',
            'DISPLAY_REQUESTED' => 'DISPLAY_REQUESTED',
            'DISPLAY_FAILED', 'SHOW_NOTIFICATION_FAILED' => 'DISPLAY_FAILED',
            'ONMESSAGE_SETUP_FAILED' => 'ONMESSAGE_SETUP_FAILED',
            'GET_TOKEN_FAILED', 'FCM_GET_TOKEN_FAILED' => 'GET_TOKEN_FAILED',
            'SERVICE_WORKER_READY_FAILED' => 'SERVICE_WORKER_READY_FAILED',
            'HARD_REREGISTER_OK' => 'HARD_REREGISTER_OK',
            default => 'CLIENT_'.$event,
        };

        $isFailure = ! in_array($event, [
            'FOREGROUND_RECEIVED',
            'ONMESSAGE_RECEIVED',
            'ONMESSAGE_ATTACHED',
            'DISPLAY_REQUESTED',
            'HARD_REREGISTER_OK',
        ], true);
        $payload = [
            'push_trace_id' => $validated['push_trace_id'] ?? null,
            'target_user_id' => $request->user()?->id,
            'token_fingerprint' => $validated['token_fingerprint'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'error_code' => $event,
            'result' => $event,
            'message' => $validated['message'] ?? null,
        ];

        if ($isFailure) {
            $diag->warning($stage, $payload);
        } else {
            $diag->info($stage, $payload);
        }

        return response()->json([
            'ok' => true,
            'event' => $event,
            'push_trace_id' => $payload['push_trace_id'] ?: (string) Str::uuid(),
        ]);
    }
}
