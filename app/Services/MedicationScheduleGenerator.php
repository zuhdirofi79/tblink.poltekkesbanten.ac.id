<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use Carbon\Carbon;
use Throwable;

class MedicationScheduleGenerator
{
    public function __construct(private PushDiagnosticLogger $diag)
    {
    }

    /**
     * Create today's HistoryJadwalObatPasien rows from eligible JadwalObat details.
     *
     * @return array{
     *     date: string,
     *     timezone: string,
     *     master_total: int,
     *     master_eligible: int,
     *     details_total: int,
     *     history_existing: int,
     *     history_created: int,
     *     skipped: int,
     *     errors: int
     * }
     */
    public function generateForDate(?Carbon $date = null, ?int $pasienId = null): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $day = ($date ?? now())->copy()->timezone($timezone)->startOfDay();
        $today = $day->toDateString();

        $masterTotal = JadwalObat::query()
            ->when($pasienId, fn ($q) => $q->where('pasien_id', $pasienId))
            ->count();

        $schedules = JadwalObat::query()
            ->with(['details', 'op', 'pasien.user'])
            ->when($pasienId, fn ($q) => $q->where('pasien_id', $pasienId))
            ->whereDate('mulai_tanggal', '<=', $today)
            ->whereDate('selesai_tanggal', '>=', $today)
            ->get();

        $stats = [
            'date' => $today,
            'timezone' => $timezone,
            'master_total' => $masterTotal,
            'master_eligible' => $schedules->count(),
            'details_total' => 0,
            'history_existing' => 0,
            'history_created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $this->diag->info('MED_GENERATOR_START', [
            'date' => $today,
            'timezone' => $timezone,
            'notification_type' => 'medication_reminder',
        ]);

        foreach ($schedules as $jadwal) {
            try {
                $this->generateForSchedule($jadwal, $today, $timezone, $stats);
            } catch (Throwable $e) {
                $stats['errors']++;
                $stats['skipped']++;
                $this->diag->exception('MED_GENERATOR_SKIP', $e, [
                    'jadwal_id' => $jadwal->id,
                    'error_code' => 'exception',
                ]);
            }
        }

        $this->diag->info('MED_SCHEDULE_SCAN', [
            'date' => $today,
            'timezone' => $timezone,
            'eligible' => $stats['master_eligible'],
            'details' => $stats['details_total'],
            'existing' => $stats['history_existing'],
            'created' => $stats['history_created'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
            'notification_type' => 'medication_reminder',
        ]);

        return $stats;
    }

    /**
     * @param  array<string, int|string>  $stats
     */
    private function generateForSchedule(JadwalObat $jadwal, string $today, string $timezone, array &$stats): void
    {
        if ($jadwal->op && ! $jadwal->op->active) {
            $stats['skipped']++;
            $this->diag->info('MED_GENERATOR_SKIP', [
                'jadwal_id' => $jadwal->id,
                'detail_id' => null,
                'result' => 'inactive',
            ]);

            return;
        }

        $pasien = $jadwal->pasien;
        if ($pasien && ($pasien->status !== 'Aktif' || ($pasien->user && $pasien->user->active === false))) {
            $stats['skipped']++;
            $this->diag->info('MED_GENERATOR_SKIP', [
                'jadwal_id' => $jadwal->id,
                'detail_id' => null,
                'pasien_id' => $pasien->id,
                'result' => 'inactive_patient',
            ]);

            return;
        }

        if (! $jadwal->pasien_id || ! $jadwal->obat_id) {
            $stats['skipped']++;
            $this->diag->info('MED_GENERATOR_SKIP', [
                'jadwal_id' => $jadwal->id,
                'detail_id' => null,
                'result' => $jadwal->pasien_id ? 'missing_obat' : 'missing_patient',
            ]);

            return;
        }

        $details = $jadwal->details;
        if ($details->isEmpty()) {
            $stats['skipped']++;
            $this->diag->info('MED_GENERATOR_SKIP', [
                'jadwal_id' => $jadwal->id,
                'detail_id' => null,
                'result' => 'no_details',
            ]);

            return;
        }

        $validTimes = [];
        foreach ($details as $detail) {
            $stats['details_total']++;
            $scheduledAt = $this->scheduledAt($today, $detail->waktu, $timezone);
            if (! $scheduledAt) {
                $stats['skipped']++;
                $this->diag->info('MED_GENERATOR_SKIP', [
                    'jadwal_id' => $jadwal->id,
                    'detail_id' => $detail->id,
                    'result' => 'invalid_time',
                ]);

                continue;
            }

            $validTimes[] = $scheduledAt->format('Y-m-d H:i:s');

            $existing = HistoryJadwalObatPasien::query()
                ->blocksGeneration()
                ->where('pasien_id', $jadwal->pasien_id)
                ->where('obat_id', $jadwal->obat_id)
                ->where('waktu', $scheduledAt->format('Y-m-d H:i:s'))
                ->first();

            if ($existing) {
                $stats['history_existing']++;
                $this->diag->info('MED_GENERATOR_SKIP', [
                    'jadwal_id' => $jadwal->id,
                    'detail_id' => $detail->id,
                    'result' => 'already_exists',
                ]);

                continue;
            }

            HistoryJadwalObatPasien::create([
                'pasien_id' => $jadwal->pasien_id,
                'obat_id' => $jadwal->obat_id,
                'detail_jadwal_obat_id' => $detail->id,
                'waktu' => $scheduledAt,
                'dosis' => $detail->dosis,
                'satuan' => $detail->satuan,
            ]);
            $stats['history_created']++;
        }

        $this->forgetStaleUpcomingToday($jadwal, $today, $validTimes);
    }

    /**
     * Drop today's still-upcoming untaken rows whose time is no longer on the master schedule.
     * Past occurrences (missed or taken) are left untouched.
     *
     * @param  list<string>  $validTimes
     */
    private function forgetStaleUpcomingToday(JadwalObat $jadwal, string $today, array $validTimes): void
    {
        $query = HistoryJadwalObatPasien::query()
            ->clinical()
            ->where('pasien_id', $jadwal->pasien_id)
            ->where('obat_id', $jadwal->obat_id)
            ->whereDate('waktu', $today)
            ->where(function ($q) {
                $q->where('sudah_minum', false)->orWhereNull('sudah_minum');
            })
            ->where('waktu', '>', now());

        if ($validTimes !== []) {
            $query->whereNotIn('waktu', $validTimes);
        }

        $query->delete();
    }

    public function scheduledAt(string $date, mixed $waktu, ?string $timezone = null): ?Carbon
    {
        $timezone = $timezone ?: (string) config('app.timezone', 'Asia/Jakarta');

        if ($waktu instanceof \DateTimeInterface) {
            $time = Carbon::instance($waktu)->format('H:i:s');
        } else {
            $time = trim((string) $waktu);
        }

        $time = preg_replace('/\..*$/', '', $time) ?? '';
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $timezone);
        } catch (Throwable) {
            return null;
        }

        return $parsed ?: null;
    }
}
