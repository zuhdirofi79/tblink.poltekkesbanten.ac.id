<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Services\FcmPushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendNotifMinumObatCmd extends Command
{
    protected $signature = 'app:send-notif-minum-obat-cmd';

    protected $description = 'Kirim Web Push pengingat minum obat sesuai jadwal';

    public function handle(FcmPushService $push): int
    {
        $now = now();
        $schedules = HistoryJadwalObatPasien::query()
            ->with(['pasien.user', 'pasien.pendamping', 'pasien.puskesmas', 'obat'])
            ->where('sudah_minum', false)
            ->where('waktu', '<=', $now)
            ->where('waktu', '>=', $now->copy()->subMinutes(30))
            ->get();

        $processed = 0;
        $sent = 0;
        $skipped = 0;

        foreach ($schedules as $sch) {
            $processed++;

            if ($sch->hasBeenNotified()) {
                $skipped++;
                continue;
            }

            $pasien = $sch->pasien;
            if (! $pasien) {
                Log::warning('[PUSH REMINDER] Skip: pasien missing', [
                    'jadwal_id' => $sch->id,
                ]);
                $skipped++;
                continue;
            }

            $obatNama = $sch->obat?->nama_obat ?: 'obat';
            $jam = Carbon::parse($sch->waktu)->timezone(config('app.timezone'))->format('H:i');
            $puskesmas = $pasien->puskesmas?->nama_puskesmas;
            $title = 'Pengingat Minum Obat '.$obatNama;
            $body = $puskesmas
                ? "Obat {$obatNama} jam {$jam} belum diminum - {$puskesmas}"
                : "Obat {$obatNama} jam {$jam} belum diminum.";
            $url = route('dashboard');

            $tokens = [];
            $pasienUser = $pasien->user;
            if ($pasienUser && filled($pasienUser->device_token)) {
                $tokens[$pasienUser->id] = $pasienUser->device_token;
            } else {
                Log::warning('[PUSH REMINDER] No active web push subscription for user', [
                    'pasien_id' => $pasien->id,
                    'user_id' => $pasienUser?->id,
                    'jadwal_id' => $sch->id,
                ]);
            }

            $pendamping = $pasien->pendamping;
            if ($pendamping && filled($pendamping->device_token)) {
                $tokens[$pendamping->id] = $pendamping->device_token;
            }

            if ($tokens === []) {
                $skipped++;
                continue;
            }

            $successCount = 0;
            foreach ($tokens as $userId => $token) {
                $result = $push->send($token, $title, $body, $url, $sch->id);
                if ($result['success']) {
                    $successCount++;
                    $sent++;
                } else {
                    Log::warning('[PUSH REMINDER] Send failed', [
                        'pasien_id' => $pasien->id,
                        'user_id' => $userId,
                        'jadwal_id' => $sch->id,
                        'reason' => $result['reason'],
                        'http_status' => $result['status'],
                    ]);
                }
            }

            Log::info('[PUSH REMINDER]', [
                'pasien_id' => $pasien->id,
                'jadwal_id' => $sch->id,
                'obat' => $obatNama,
                'scheduled_at' => optional($sch->waktu)->toDateTimeString(),
                'sent_count' => $successCount,
            ]);
        }

        $this->info("Processed {$processed}, sent {$sent}, skipped {$skipped}");

        return self::SUCCESS;
    }
}
