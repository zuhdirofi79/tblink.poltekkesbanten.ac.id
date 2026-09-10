<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PushAckController extends Controller
{
    public function __invoke(Request $request)
    {
        $trace = (string) $request->query('t', '');
        $stage = (string) $request->query('s', '');
        $exp = (int) $request->query('e', 0);
        $sig = (string) $request->query('sig', '');

        $allowed = ['browser_received', 'display_requested'];
        if ($trace === '' || ! in_array($stage, $allowed, true) || $exp < time() || $sig === '') {
            return response()->json(['ok' => false], 422);
        }

        $payload = $trace.'|'.$stage.'|'.$exp;
        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));
        if (! hash_equals($expected, $sig)) {
            return response()->json(['ok' => false], 403);
        }

        Cache::put('push_ack:'.$trace.':'.$stage, now()->toIso8601String(), now()->addHours(6));
        Log::info('[PUSH_ACK]', [
            'trace_id' => $trace,
            'stage' => $stage,
        ]);

        return response()->json(['ok' => true, 'stage' => $stage]);
    }
}
