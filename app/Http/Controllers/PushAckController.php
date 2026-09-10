<?php

namespace App\Http\Controllers;

use App\Services\PushDiagnosticLogger;
use Illuminate\Http\Request;

class PushAckController extends Controller
{
    public function __invoke(Request $request, PushDiagnosticLogger $diag)
    {
        $trace = (string) $request->query('t', '');
        $stage = (string) $request->query('s', '');
        $exp = (int) $request->query('e', 0);
        $sig = (string) $request->query('sig', '');
        $err = substr(preg_replace('/[^\w\s.\-:()\/]/', '', (string) $request->query('err', '')) ?? '', 0, 120);

        $allowed = [
            'raw_push_received',
            'payload_parsed',
            'payload_parse_failed',
            'browser_received',
            'display_requested',
            'display_failed',
            'notification_clicked',
        ];
        if ($trace === '' || ! in_array($stage, $allowed, true) || $exp < time() || $sig === '') {
            $diag->warning('ACK_REJECTED', [
                'push_trace_id' => $trace ?: null,
                'result' => 'invalid_ack',
                'error_code' => 'invalid_ack',
            ]);

            return response()->json(['ok' => false], 422);
        }

        $payload = $trace.'|'.$stage.'|'.$exp;
        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));
        if (! hash_equals($expected, $sig)) {
            $diag->warning('ACK_REJECTED', [
                'push_trace_id' => $trace,
                'result' => 'invalid_signature',
                'error_code' => 'invalid_signature',
            ]);

            return response()->json(['ok' => false], 403);
        }

        $stageMap = [
            'raw_push_received' => 'RAW_PUSH_RECEIVED',
            'payload_parsed' => 'PAYLOAD_PARSED',
            'payload_parse_failed' => 'PAYLOAD_PARSE_FAILED',
            'browser_received' => 'BROWSER_RECEIVED',
            'display_requested' => 'DISPLAY_REQUESTED',
            'display_failed' => 'DISPLAY_FAILED',
            'notification_clicked' => 'NOTIFICATION_CLICKED',
        ];
        $logStage = $stageMap[$stage];
        $level = in_array($stage, ['display_failed', 'payload_parse_failed'], true) ? 'warning' : 'info';
        $diag->{$level}($logStage, [
            'push_trace_id' => $trace,
            'result' => $logStage,
            'message' => $err !== '' ? $err : null,
        ]);

        cache()->put('push_ack:'.$trace.':'.$stage, now()->toIso8601String(), now()->addHours(6));

        return response()->json(['ok' => true, 'stage' => $stage]);
    }
}
