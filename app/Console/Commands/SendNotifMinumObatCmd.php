<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Services\FcmPushService;
use App\Services\PushDiagnosticLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendNotifMinumObatCmd extends Command
{
    protected $signature = 'app:send-notif-minum-obat-cmd';

    protected $description = 'Kirim Web Push pengingat minum obat sesuai jadwal';

    public function handle(FcmPushService $push, PushDiagnosticLogger $diag): int
    {
        $now = now();
        $timezone = (string) config('app.timezone');
        $windowStart = $now->copy()->subMinutes(60);

        $candidates = HistoryJadwalObatPasien::query()
            ->with(['pasien.user', 'pasien.pendamping', 'pasien.puskesmas', 'obat'])
            ->where(function ($q) {
                $q->where('sudah_minum', false)->orWhereNull('sudah_minum');
            })
            ->where('waktu', '<=', $now)
            ->where('waktu', '>=', $windowStart)
            ->get();

        $scanPayload = [
            'timestamp' => $now->toIso8601String(),
            'timezone' => $timezone,
            'current_time' => $now->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'window_start' => $windowStart->toDateTimeString(),
            'candidate_count' => $candidates->count(),
            'notification_type' => 'medication_reminder',
        ];
        $diag->info('MEDICATION_SCAN', $scanPayload);
        $diag->info('MED_REMINDER_SCAN', $scanPayload);

        if ($candidates->isEmpty()) {
            $diag->info('MED_REMINDER_NONE_DUE', [
                'now' => $now->toDateTimeString(),
                'window_start' => $windowStart->toDateTimeString(),
                'candidate_count' => 0,
                'notification_type' => 'medication_reminder',
                'result' => 'NONE_DUE',
            ]);
            $this->info('Processed 0, sent 0, skipped 0');

            return self::SUCCESS;
        }

        $processed = 0;
        $sent = 0;
        $skipped = 0;

        foreach ($candidates as $sch) {
            $processed++;
            $traceId = (string) Str::uuid();

            try {
                $pasien = $sch->pasien;
                if (! $pasien) {
                    $diag->warning('TARGET_RESOLVE', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'result' => 'pasien_missing',
                        'error_code' => 'pasien_missing',
                    ]);
                    $skipped++;
                    continue;
                }

                $pasienUser = $pasien->user;
                if ($pasien->status !== 'Aktif' || ($pasienUser && $pasienUser->active === false)) {
                    $diag->info('TARGET_RESOLVE', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'pasien_id' => $pasien->id,
                        'target_user_id' => $pasienUser?->id,
                        'result' => 'inactive_patient',
                    ]);
                    $skipped++;
                    continue;
                }

                $obatNama = $sch->obat?->nama_obat ?: 'obat';
                $scheduled = $sch->waktu instanceof Carbon
                    ? $sch->waktu->copy()
                    : Carbon::parse($sch->waktu);
                $jam = $scheduled->timezone($timezone)->format('H:i');
                $diag->info('MEDICATION_CANDIDATE', [
                    'history_id' => $sch->id,
                    'pasien_id' => $sch->pasien_id,
                    'user_id' => $pasienUser?->id,
                    'obat_id' => $sch->obat_id,
                    'scheduled_time' => optional($sch->waktu)->toDateTimeString(),
                    'sudah_minum' => (bool) $sch->sudah_minum,
                    'notified' => $sch->hasBeenNotified($pasienUser?->id),
                    'source' => $sch->source,
                ]);
                $isTest = $sch->isManualTest();
                $notificationType = $isTest ? 'medication_reminder_test' : 'medication_reminder';
                $title = 'Pengingat Minum Obat '.$obatNama;
                $body = $isTest
                    ? "{$obatNama} pukul {$jam} belum ditandai sudah diminum."
                    : "Obat {$obatNama} jam {$jam} belum diminum";
                if (! $isTest) {
                    $puskesmasNama = $pasien->puskesmas?->nama_puskesmas;
                    if (filled($puskesmasNama)) {
                        $body .= "\n- {$puskesmasNama}";
                    }
                }
                $url = route('dashboard');

                $targets = [];
                if ($pasienUser) {
                    $targets[$pasienUser->id] = ['user' => $pasienUser, 'role' => 'patient'];
                }
                if (! $isTest) {
                    $pendamping = $pasien->pendamping;
                    if ($pendamping) {
                        $targets[$pendamping->id] = ['user' => $pendamping, 'role' => 'companion'];
                    }
                }

                if ($targets === []) {
                    $diag->warning('NO_SUBSCRIPTION', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'subscription_count' => 0,
                        'result' => 'no_target_user',
                    ]);
                    $skipped++;
                    continue;
                }

                $successCount = 0;
                $foundAny = false;
                $attempted = 0;

                foreach ($targets as $userId => $target) {
                    $targetUser = $target['user'];
                    if ($sch->hasBeenNotified((int) $userId)) {
                        $diag->info('TARGET_RESOLVE', [
                            'push_trace_id' => $traceId,
                            'history_id' => $sch->id,
                            'target_user_id' => $userId,
                            'role' => $target['role'],
                            'result' => 'already_notified',
                        ]);
                        $skipped++;
                        continue;
                    }

                    $attempted++;
                    $subscriptionCount = count($push->tokensForUser($targetUser));
                    $fingerprints = $push->fingerprintsForUser($targetUser);
                    $diag->info('MED_REMINDER_DUE', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'scheduled_at' => optional($sch->waktu)->toDateTimeString(),
                        'target_user_id' => $userId,
                        'subscription_count' => $subscriptionCount,
                        'notification_type' => $notificationType,
                    ]);
                    $diag->info('MEDICATION_PUSH_ATTEMPT', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'user_id' => $userId,
                        'token_fingerprint' => implode(',', $fingerprints),
                        'subscription_count' => $subscriptionCount,
                    ]);

                    $result = $push->sendToUser(
                        $targetUser,
                        $title,
                        $body,
                        $url,
                        $sch->id,
                        $traceId,
                        $notificationType,
                    );
                    $foundAny = $foundAny || ($result['subscriptions_found'] ?? 0) > 0;
                    if ($result['success']) {
                        $successCount += (int) ($result['subscriptions_sent'] ?? 0);
                        $sent += (int) ($result['subscriptions_sent'] ?? 0);
                        $diag->info('MEDICATION_PUSH_SUCCESS', [
                            'push_trace_id' => $traceId,
                            'history_id' => $sch->id,
                            'user_id' => $userId,
                            'fcm_message_id' => $result['message_id'] ?? null,
                        ]);
                    } else {
                        $diag->warning('MEDICATION_PUSH_FAILED', [
                            'push_trace_id' => $traceId,
                            'history_id' => $sch->id,
                            'user_id' => $userId,
                            'reason' => $result['reason'] ?? 'unknown',
                        ]);
                    }
                }

                if ($attempted > 0 && ! $foundAny) {
                    $diag->warning('NO_SUBSCRIPTION', [
                        'push_trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'target_user_id' => $pasienUser?->id,
                        'subscription_count' => 0,
                        'result' => 'NO_SUBSCRIPTION',
                    ]);
                }

                $diag->info('MED_REMINDER_RESULT', [
                    'push_trace_id' => $traceId,
                    'history_id' => $sch->id,
                    'result' => $successCount > 0 ? 'FCM_ACCEPTED' : 'none_sent',
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                $diag->exception('MED_REMINDER_EXCEPTION', $e, [
                    'push_trace_id' => $traceId,
                    'history_id' => $sch->id,
                ]);
            }
        }

        $this->info("Processed {$processed}, sent {$sent}, skipped {$skipped}");

        return self::SUCCESS;
    }
}
