<?php

namespace App\Console\Commands;

use App\Models\JadwalVisit;
use App\Services\FcmPushService;
use App\Services\VisitReminderTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendJadwalVisitReminders extends Command
{
    protected $signature = 'app:send-jadwal-visit-reminders';

    protected $description = 'Kirim Web Push pengingat jadwal berobat (JadwalVisit)';

    public function handle(FcmPushService $push, VisitReminderTracker $tracker): int
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

        Log::info('[VISIT_REMINDER_SCAN]', [
            'now' => $now->toDateTimeString(),
            'timezone' => $timezone,
            'candidates' => $candidates->count(),
        ]);

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
                    Log::warning('[VISIT_REMINDER_DUE]', [
                        'trace_id' => $traceId,
                        'visit_id' => $visit->id,
                        'patient_user_id' => null,
                        'reason' => 'patient_user_missing',
                    ]);
                    $skipped++;
                    continue;
                }

                $due++;
                $jam = Carbon::parse($visit->tgl_visit)->timezone($timezone)->format('H:i');
                $title = 'Pengingat Jadwal Berobat';
                $body = 'Anda memiliki jadwal berobat pukul '.$jam.'.';
                $url = route('pasien.visit-pasien.index');

                $targets = [$patientUser->id => $patientUser];
                $pendamping = $pasien?->pendamping;
                if ($pendamping) {
                    $targets[$pendamping->id] = $pendamping;
                }

                $successCount = 0;
                $subscriptionCount = 0;

                foreach ($targets as $userId => $targetUser) {
                    $count = count($push->tokensForUser($targetUser));
                    $subscriptionCount += $count;

                    Log::info('[VISIT_REMINDER_DUE]', [
                        'trace_id' => $traceId,
                        'visit_id' => $visit->id,
                        'patient_user_id' => $patientUser->id,
                        'user_id' => $userId,
                        'scheduled_at' => optional($visit->tgl_visit)->toDateTimeString(),
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
                    } else {
                        Log::warning('[VISIT_REMINDER]', [
                            'trace_id' => $traceId,
                            'visit_id' => $visit->id,
                            'patient_user_id' => $patientUser->id,
                            'reason' => $result['reason'],
                            'http_status' => $result['status'] ?? null,
                            'token_fingerprint' => $result['token_fingerprint'] ?? null,
                        ]);
                    }
                }

                if ($successCount > 0) {
                    $tracker->markSent($visit);
                }

                Log::info('[VISIT_REMINDER]', [
                    'trace_id' => $traceId,
                    'visit_id' => $visit->id,
                    'patient_user_id' => $patientUser->id,
                    'scheduled_at' => optional($visit->tgl_visit)->toDateTimeString(),
                    'subscription_count' => $subscriptionCount,
                    'sent_count' => $successCount,
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                Log::error('[VISIT_REMINDER] exception', [
                    'trace_id' => $traceId,
                    'visit_id' => $visit->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Visit reminders due {$due}, sent {$sent}, skipped {$skipped}");

        return self::SUCCESS;
    }
}
