<?php

namespace App\Console\Commands;

use App\Models\DetailJadwalObat;
use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\ObatPasien;
use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Console\Command;

class MedicationReminderAuditCommand extends Command
{
    protected $signature = 'app:medication-reminder-audit {username=rofite1}';

    protected $description = 'Read-only audit of medication reminder pipeline for one patient user';

    public function handle(FcmPushService $push): int
    {
        $timezone = (string) config('app.timezone');
        $now = now()->timezone($timezone);
        $windowStart = $now->copy()->subMinutes(60);
        $username = (string) $this->argument('username');

        $this->info('timezone: '.$timezone);
        $this->info('now: '.$now->toDateTimeString());
        $this->info('due_window_start: '.$windowStart->toDateTimeString());

        $user = User::query()->where('username', $username)->first();
        if (! $user) {
            $this->error('user not found');

            return self::FAILURE;
        }

        $pasien = $user->pasien;
        if (! $pasien) {
            $this->error('pasien not found for username');

            return self::FAILURE;
        }

        $this->table(['key', 'value'], [
            ['username', $username],
            ['user_id', (string) $user->id],
            ['pasien_id', (string) $pasien->id],
            ['obat_pasiens', (string) ObatPasien::where('pasien_id', $pasien->id)->count()],
            ['jadwal_obats', (string) JadwalObat::where('pasien_id', $pasien->id)->count()],
            ['detail_jadwal_obats', (string) DetailJadwalObat::whereIn(
                'jadwal_obat_id',
                JadwalObat::where('pasien_id', $pasien->id)->pluck('id')
            )->count()],
            ['history_total', (string) HistoryJadwalObatPasien::where('pasien_id', $pasien->id)->count()],
            ['subscription_count', (string) count($push->tokensForUser($user))],
            ['token_fingerprints', implode(',', $push->fingerprintsForUser($user))],
        ]);

        $today = HistoryJadwalObatPasien::query()
            ->with('obat')
            ->where('pasien_id', $pasien->id)
            ->whereDate('waktu', $now->toDateString())
            ->orderBy('waktu')
            ->get();

        $rows = $today->map(function (HistoryJadwalObatPasien $h) use ($now, $windowStart, $user) {
            $due = (! $h->sudah_minum)
                && $h->waktu <= $now
                && $h->waktu >= $windowStart
                && ! $h->hasBeenNotified($user->id);

            return [
                'id' => $h->id,
                'pasien_id' => $h->pasien_id,
                'obat_id' => $h->obat_id,
                'obat' => $h->obat?->nama_obat,
                'waktu' => optional($h->waktu)->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'),
                'sudah_minum' => $h->sudah_minum ? '1' : '0',
                'notified' => $h->hasBeenNotified($user->id) ? '1' : '0',
                'source' => $h->source,
                'would_be_due' => $due ? 'YES' : 'NO',
            ];
        })->all();

        $this->info('history hari ini:');
        if ($rows === []) {
            $this->warn('NONE');
        } else {
            $this->table(array_keys($rows[0]), $rows);
        }

        $this->comment('Read-only. Does not send FCM and does not change sudah_minum / tokens / history.');

        return self::SUCCESS;
    }
}
