<?php

namespace App\Console\Commands;

use App\Models\JadwalVisit;
use App\Services\FcmPushService;
use App\Services\PushDiagnosticLogger;
use App\Services\VisitReminderTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendJadwalVisitReminders extends Command
{
    protected $signature = 'app:send-jadwal-visit-reminders';

    protected $description = 'Kirim Web Push pengingat jadwal berobat (JadwalVisit)';

    public function handle(FcmPushService $push, VisitReminderTracker $tracker, PushDiagnosticLogger $diag): int
    {
        $now = now();
        $timezone = (string) config('app.timezone');
        $windowStart = $now->copy()->subMinutes(30);

        $candidates = JadwalVisit::query()
            ->with(['pasien.user', 'pasien.pendamping'])
            ->where('active', true)
            ->where('tgl_visit', '<=', $now)
            ->where('tgl_visit', '>=', $windowStart)
            ->get();

        $diag->info('VISIT_REMINDER_SCAN', [
            'now' => $now->toDateTimeString(),
            'window_start' => $windowStart->toDateTimeString(),
            'candidate_count' => $candidates->count(),
            'notification_type' => 'visit_reminder',
        ]);

        if ($candidates->isEmpty()) {
            $diag->info('VISIT_REMINDER_NONE_DUE', [
                'now' => $now->toDateTimeString(),
                'candidate_count' => 0,
                'notification_type' => 'visit_reminder',
                'result' => 'NONE_DUE',
            ]);
            $this->info('Visit reminders due 0, sent 0, skipped 0');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $due = 0;

        foreach ($candidates as $visit) {
            $traceId = (string) Str::uuid();

            try {
                if ($tracker->alreadySent($visit)) {
                    $skipped++;
                    continue;
                }

                $pasien = $visit->pasien;
                $patientUser = $pasien?->user;
                if (! $patientUser) {
                    $diag->warning('TARGET_RESOLVE', [
                        'push_trace_id' => $traceId,
                        'visit_id' => $visit->id,
                        'notification_type' => 'visit_reminder',
                        'result' => 'patient_user_missing',
                    ]);
                    $skipped++;
                    continue;
                }

                $due++;
                $diag->info('VISIT_REMINDER_DUE', [
                    'push_trace_id' => $traceId,
                    'visit_id' => $visit->id,
                    'scheduled_at' => optional($visit->tgl_visit)->toDateTimeString(),
                    'notification_type' => 'visit_reminder',
                ]);

                $jam = Carbon::parse($visit->tgl_visit)->timezone($timezone)->format('H:i');
                $title = 'Pengingat Jadwal Berobat';
                $body = 'Anda memiliki jadwal berobat pukul '.$jam.'.';
                $url = route('pasien.visit-pasien.index');

                $targets = [$patientUser->id => ['user' => $patientUser, 'role' => 'patient']];
                $pendamping = $pasien?->pendamping;
                if ($pendamping) {
                    $targets[$pendamping->id] = ['user' => $pendamping, 'role' => 'companion'];
                }

                $successCount = 0;
                $subscriptionCount = 0;

                foreach ($targets as $userId => $target) {
                    $targetUser = $target['user'];
                    $count = count($push->tokensForUser($targetUser));
                    $subscriptionCount += $count;
                    $diag->info('TARGET_RESOLVE', [
                        'push_trace_id' => $traceId,
                        'visit_id' => $visit->id,
                        'target_user_id' => $userId,
                        'role' => $target['role'],
                        'notification_type' => 'visit_reminder',
                        'subscription_count' => $count,
                    ]);

                    $result = $push->sendToUser(
                        $targetUser,
                        $title,
                        $body,
                        $url,
                        0,
                        $traceId,
                        'visit_reminder',
                        [
                            'tag' => 'visit-'.$visit->id,
                            'visit_id' => (string) $visit->id,
                        ],
                    );

                    if ($result['success']) {
                        $successCount += (int) ($result['subscriptions_sent'] ?? 0);
                        $sent += (int) ($result['subscriptions_sent'] ?? 0);
                    }
                }

                if ($successCount > 0) {
                    $tracker->markSent($visit);
                }

                $diag->info('VISIT_REMINDER_RESULT', [
                    'push_trace_id' => $traceId,
                    'visit_id' => $visit->id,
                    'target_user_id' => $patientUser->id,
                    'subscription_count' => $subscriptionCount,
                    'result' => $successCount > 0 ? 'FCM_ACCEPTED' : 'none_sent',
                    'notification_type' => 'visit_reminder',
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                $diag->exception('VISIT_REMINDER_EXCEPTION', $e, [
                    'push_trace_id' => $traceId,
                    'visit_id' => $visit->id,
                    'notification_type' => 'visit_reminder',
                ]);
            }
        }

        $this->info("Visit reminders due {$due}, sent {$sent}, skipped {$skipped}");

        return self::SUCCESS;
    }
}
