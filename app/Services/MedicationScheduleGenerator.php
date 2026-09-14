<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\ObatPasien;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MedicationScheduleGenerator
{
    public function __construct(private PushDiagnosticLogger $diag)
    {
    }

    /**
     * Create today's HistoryJadwalObatPasien rows from eligible ObatPasien + JadwalObat details.
     *
     * @return array{
     *     date: string,
     *     timezone: string,
     *     master_total: int,
     *     master_eligible: int,
     *     details_total: int,
     *     history_existing: int,
     *     history_created: int,
     *     history_suppressed: int,
     *     skipped: int,
     *     errors: int
     * }
     */
    public function generateForDate(?Carbon $date = null, ?int $pasienId = null): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $day = ($date ?? now())->copy()->timezone($timezone)->startOfDay();
        $today = $day->toDateString();
        $userId = auth()->id();

        $assignments = ObatPasien::query()
            ->with(['jadwal.details', 'pasien.user'])
            ->when($pasienId, fn ($q) => $q->where('pasien_id', $pasienId))
            ->get();

        $stats = [
            'date' => $today,
            'timezone' => $timezone,
            'master_total' => $assignments->count(),
            'master_eligible' => 0,
            'details_total' => 0,
            'history_existing' => 0,
            'history_created' => 0,
            'history_suppressed' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        Log::info('[MEDICATION_GENERATE_START]', [
            'patient_id' => $pasienId,
            'date' => $today,
            'user_id' => $userId,
        ]);

        $this->diag->info('MED_GENERATOR_START', [
            'date' => $today,
            'timezone' => $timezone,
            'pasien_id' => $pasienId,
            'notification_type' => 'medication_reminder',
        ]);

        $pruneTimes = [];

        foreach ($assignments as $assignment) {
            try {
                $this->generateForAssignment($assignment, $today, $timezone, $stats, $pruneTimes, $userId);
            } catch (Throwable $e) {
                $stats['errors']++;
                $stats['skipped']++;
                Log::error('[MEDICATION_GENERATE_SKIPPED]', [
                    'patient_id' => $assignment->pasien_id,
                    'obat_pasien_id' => $assignment->id,
                    'obat_id' => $assignment->obat_id,
                    'reason' => 'exception',
                ]);
                $this->diag->exception('MED_GENERATOR_SKIP', $e, [
                    'obat_pasien_id' => $assignment->id,
                    'error_code' => 'exception',
                ]);
            }
        }

        foreach ($pruneTimes as $key => $times) {
            [$prunePasienId, $pruneObatId] = array_map('intval', explode(':', $key, 2));
            $this->forgetStaleUpcomingToday($prunePasienId, $pruneObatId, $today, array_values(array_unique($times)));
        }

        Log::info('[MEDICATION_GENERATE_COMPLETE]', [
            'patient_id' => $pasienId,
            'date' => $today,
            'user_id' => $userId,
            'created' => $stats['history_created'],
            'existing' => $stats['history_existing'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
            'suppressed' => $stats['history_suppressed'],
        ]);

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
     * @param  array<string, list<string>>  $pruneTimes
     */
    private function generateForAssignment(
        ObatPasien $assignment,
        string $today,
        string $timezone,
        array &$stats,
        array &$pruneTimes,
        mixed $userId,
    ): void {
        $jadwal = $assignment->jadwal;
        $item = [
            'patient_id' => $assignment->pasien_id,
            'obat_pasien_id' => $assignment->id,
            'obat_id' => $assignment->obat_id,
            'detail_id' => null,
            'scheduled_time' => null,
            'eligible' => false,
            'reason' => null,
        ];

        if (! $assignment->active) {
            $stats['skipped']++;
            $item['reason'] = 'inactive';
            $this->logItem($item);

            return;
        }

        if (! $jadwal) {
            $stats['skipped']++;
            $item['reason'] = 'no_jadwal';
            $this->logItem($item);

            return;
        }

        if (! $this->isWithinTreatmentPeriod($jadwal, $today)) {
            $stats['skipped']++;
            $item['reason'] = 'outside_period';
            $this->logItem($item);

            return;
        }

        $pasien = $assignment->pasien ?? $jadwal->pasien;
        if ($pasien && ($pasien->status !== 'Aktif' || ($pasien->user && $pasien->user->active === false))) {
            $stats['skipped']++;
            $item['reason'] = 'inactive_patient';
            $this->logItem($item);

            return;
        }

        $pasienId = (int) ($jadwal->pasien_id ?: $assignment->pasien_id);
        $obatId = (int) ($jadwal->obat_id ?: $assignment->obat_id);
        if (! $pasienId || ! $obatId) {
            $stats['skipped']++;
            $item['reason'] = $pasienId ? 'missing_obat' : 'missing_patient';
            $this->logItem($item);

            return;
        }

        $details = $jadwal->details;
        if ($details->isEmpty()) {
            $stats['skipped']++;
            $item['reason'] = 'no_details';
            $this->logItem($item);

            return;
        }

        $stats['master_eligible']++;
        $validTimes = [];

        foreach ($details as $detail) {
            $stats['details_total']++;
            $scheduledAt = $this->scheduledAt($today, $detail->waktu, $timezone);
            $rowItem = [
                'patient_id' => $pasienId,
                'obat_pasien_id' => $assignment->id,
                'obat_id' => $obatId,
                'detail_id' => $detail->id,
                'scheduled_time' => $scheduledAt?->timezone($timezone)->format('Y-m-d H:i:s'),
                'eligible' => false,
                'reason' => null,
            ];

            if (! $scheduledAt) {
                $stats['skipped']++;
                $rowItem['reason'] = 'invalid_time';
                $this->logItem($rowItem);

                continue;
            }

            $validTimes[] = $scheduledAt->format('Y-m-d H:i:s');

            $existing = HistoryJadwalObatPasien::query()
                ->blocksGeneration()
                ->where('pasien_id', $pasienId)
                ->where('obat_id', $obatId)
                ->where('waktu', $scheduledAt->format('Y-m-d H:i:s'))
                ->first();

            if ($existing) {
                $rowItem['eligible'] = true;
                if ($existing->source === HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED) {
                    $stats['history_suppressed']++;
                    $rowItem['reason'] = 'manually_deleted';
                } else {
                    $stats['history_existing']++;
                    $rowItem['reason'] = 'already_exists';
                }
                $this->logItem($rowItem);
                Log::info('[MEDICATION_GENERATE_EXISTING]', [
                    'history_id' => $existing->id,
                    'patient_id' => $pasienId,
                    'obat_id' => $obatId,
                    'scheduled_at' => $rowItem['scheduled_time'],
                    'reason' => $rowItem['reason'],
                    'user_id' => $userId,
                ]);

                continue;
            }

            $history = HistoryJadwalObatPasien::create([
                'pasien_id' => $pasienId,
                'obat_id' => $obatId,
                'detail_jadwal_obat_id' => $detail->id,
                'waktu' => $scheduledAt,
                'dosis' => $detail->dosis,
                'satuan' => $detail->satuan,
                'sudah_minum' => false,
                'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
            ]);
            $stats['history_created']++;
            $rowItem['eligible'] = true;
            $rowItem['reason'] = 'created';
            $this->logItem($rowItem);
            Log::info('[MEDICATION_GENERATE_CREATED]', [
                'history_id' => $history->id,
                'patient_id' => $pasienId,
                'obat_id' => $obatId,
                'scheduled_at' => $rowItem['scheduled_time'],
                'user_id' => $userId,
            ]);
        }

        if ($validTimes !== []) {
            $key = $pasienId.':'.$obatId;
            $pruneTimes[$key] = array_merge($pruneTimes[$key] ?? [], $validTimes);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function logItem(array $item): void
    {
        Log::info('[MEDICATION_GENERATE_ITEM]', $item);
        if (! ($item['eligible'] ?? false)) {
            Log::info('[MEDICATION_GENERATE_SKIPPED]', $item);
            $this->diag->info('MED_GENERATOR_SKIP', [
                'obat_pasien_id' => $item['obat_pasien_id'] ?? null,
                'detail_id' => $item['detail_id'] ?? null,
                'result' => $item['reason'] ?? 'skipped',
            ]);
        }
    }

    private function isWithinTreatmentPeriod(JadwalObat $jadwal, string $today): bool
    {
        $start = $jadwal->mulai_tanggal
            ? Carbon::parse($jadwal->mulai_tanggal)->timezone((string) config('app.timezone', 'Asia/Jakarta'))->toDateString()
            : null;
        $end = $jadwal->selesai_tanggal
            ? Carbon::parse($jadwal->selesai_tanggal)->timezone((string) config('app.timezone', 'Asia/Jakarta'))->toDateString()
            : null;

        if (! $start || ! $end) {
            return false;
        }

        return $start <= $today && $end >= $today;
    }

    /**
     * Drop today's still-upcoming untaken rows whose time is no longer on any active schedule
     * for this patient + medicine. Past occurrences (missed or taken) are left untouched.
     *
     * @param  list<string>  $validTimes
     */
    private function forgetStaleUpcomingToday(int $pasienId, int $obatId, string $today, array $validTimes): void
    {
        if ($validTimes === []) {
            return;
        }

        $query = HistoryJadwalObatPasien::query()
            ->clinical()
            ->where('pasien_id', $pasienId)
            ->where('obat_id', $obatId)
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
