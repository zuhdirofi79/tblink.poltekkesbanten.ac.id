<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Services\FcmPushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendNotifMinumObatCmd extends Command
{
    protected $signature = 'app:send-notif-minum-obat-cmd';

    protected $description = 'Kirim Web Push pengingat minum obat sesuai jadwal';

    public function handle(FcmPushService $push): int
    {
        $now = now();
        $timezone = (string) config('app.timezone');

        $candidates = HistoryJadwalObatPasien::query()
            ->with(['pasien.user', 'pasien.pendamping', 'pasien.puskesmas', 'obat'])
            ->where('sudah_minum', false)
            ->where('waktu', '<=', $now)
            ->where('waktu', '>=', $now->copy()->subMinutes(30))
            ->get();

        Log::info('[REMINDER_SCAN]', [
            'now' => $now->toDateTimeString(),
            'timezone' => $timezone,
            'candidates' => $candidates->count(),
        ]);

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $eligible = 0;

        foreach ($candidates as $sch) {
            $processed++;
            $traceId = (string) Str::uuid();

            try {
                $pasien = $sch->pasien;
                if (! $pasien) {
                    Log::warning('[REMINDER_ITEM]', [
                        'trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'status' => 'pasien_missing',
                    ]);
                    $skipped++;
                    continue;
                }

                $obatNama = $sch->obat?->nama_obat ?: 'obat';
                $jam = Carbon::parse($sch->waktu)->timezone($timezone)->format('H:i');
                $title = 'Pengingat Minum Obat '.$obatNama;
                $body = "{$obatNama} pukul {$jam} belum ditandai sudah diminum.";
                $url = route('dashboard');

                $targets = [];
                $pasienUser = $pasien->user;
                if ($pasienUser) {
                    $targets[$pasienUser->id] = $pasienUser;
                }
                $pendamping = $pasien->pendamping;
                if ($pendamping) {
                    $targets[$pendamping->id] = $pendamping;
                }

                if ($targets === []) {
                    $skipped++;
                    continue;
                }

                $successCount = 0;
                $foundAny = false;
                $attempted = 0;

                foreach ($targets as $userId => $targetUser) {
                    if ($sch->hasBeenNotified((int) $userId)) {
                        Log::info('[REMINDER_ITEM]', [
                            'trace_id' => $traceId,
                            'history_id' => $sch->id,
                            'user_id' => $userId,
                            'scheduled_at' => optional($sch->waktu)->toDateTimeString(),
                            'status' => 'already_notified',
                            'already_notified' => true,
                        ]);
                        $skipped++;
                        continue;
                    }

                    $attempted++;
                    $eligible++;
                    $subCount = count($push->tokensForUser($targetUser));
                    Log::info('[MED_SCHEDULE_DUE]', [
                        'trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'user_id' => $userId,
                        'scheduled_at' => optional($sch->waktu)->toDateTimeString(),
                        'status' => 'eligible',
                        'already_notified' => false,
                        'push_subscription_count' => $subCount,
                    ]);

                    $result = $push->sendToUser(
                        $targetUser,
                        $title,
                        $body,
                        $url,
                        $sch->id,
                        $traceId,
                        'medication_reminder',
                    );
                    $foundAny = $foundAny || ($result['subscriptions_found'] ?? 0) > 0;
                    if ($result['success']) {
                        $successCount += (int) ($result['subscriptions_sent'] ?? 0);
                        $sent += (int) ($result['subscriptions_sent'] ?? 0);
                    } else {
                        Log::warning('[REMINDER] Send failed', [
                            'trace_id' => $traceId,
                            'history_id' => $sch->id,
                            'user_id' => $userId,
                            'reason' => $result['reason'],
                            'http_status' => $result['status'],
                            'token_fingerprint' => $result['token_fingerprint'] ?? null,
                        ]);
                    }
                }

                if ($attempted > 0 && ! $foundAny) {
                    Log::warning('[REMINDER] No active web push subscription', [
                        'trace_id' => $traceId,
                        'history_id' => $sch->id,
                        'pasien_id' => $pasien->id,
                        'user_id' => $pasienUser?->id,
                    ]);
                }

                Log::info('[REMINDER]', [
                    'trace_id' => $traceId,
                    'history_id' => $sch->id,
                    'sent_count' => $successCount,
                ]);
            } catch (\Throwable $e) {
                $skipped++;
                Log::error('[REMINDER_ITEM] exception', [
                    'trace_id' => $traceId,
                    'history_id' => $sch->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[REMINDER_SCAN]', [
            'now' => $now->toDateTimeString(),
            'timezone' => $timezone,
            'candidates' => $candidates->count(),
            'eligible' => $eligible,
            'processed' => $processed,
            'sent' => $sent,
            'skipped' => $skipped,
        ]);

        $this->info("Processed {$processed}, sent {$sent}, skipped {$skipped}");

        return self::SUCCESS;
    }
}
