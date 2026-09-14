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
     *     errors: int,
     *     skip_reasons: array<string, int>
     * }
     */
    public function generateForDate(?Carbon $date = null, ?int $pasienId = null, bool $restoreManualDeletions = false): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $day = ($date ?? now())->copy()->timezone($timezone)->startOfDay();
        $today = $day->toDateString();
        $userId = auth()->id();

        $assignments = ObatPasien::query()
            ->with(['jadwal.details', 'pasien.user', 'obat'])
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
            'skip_reasons' => [],
        ];

        Log::info('[MEDICATION_GENERATE_START]', [
            'patient_id' => $pasienId,
            'date' => $today,
            'timezone' => $timezone,
            'user_id' => $userId,
            'restore_manual_deletions' => $restoreManualDeletions,
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
                $this->generateForAssignment(
                    $assignment,
                    $today,
                    $timezone,
                    $stats,
                    $pruneTimes,
                    $userId,
                    $restoreManualDeletions,
                );
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->bumpSkip($stats, 'exception');
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
            'timezone' => $timezone,
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
     * @param  array<string, mixed>  $stats
     * @param  array<string, list<string>>  $pruneTimes
     */
    private function generateForAssignment(
        ObatPasien $assignment,
        string $today,
        string $timezone,
        array &$stats,
        array &$pruneTimes,
        mixed $userId,
        bool $restoreManualDeletions,
    ): void {
        $jadwal = $assignment->jadwal;
        $detailTimes = $jadwal?->details
            ? $jadwal->details->map(fn ($d) => $this->timeLabel($d->waktu))->filter()->values()->all()
            : [];

        $check = [
            'patient_id' => $assignment->pasien_id,
            'obat_pasien_id' => $assignment->id,
            'obat_id' => $assignment->obat_id,
            'nama_obat' => $assignment->obat?->nama_obat,
            'active' => $assignment->active ? 1 : 0,
            'mulai_tanggal' => $jadwal?->mulai_tanggal
                ? Carbon::parse($jadwal->mulai_tanggal)->timezone($timezone)->toDateString()
                : null,
            'selesai_tanggal' => $jadwal?->selesai_tanggal
                ? Carbon::parse($jadwal->selesai_tanggal)->timezone($timezone)->toDateString()
                : null,
            'today' => $today,
            'timezone' => $timezone,
            'jadwal_obat_id' => $jadwal?->id,
            'detail_count' => $jadwal?->details?->count() ?? 0,
            'detail_times' => $detailTimes,
            'decision' => 'SKIP',
            'reason' => null,
        ];

        if (! $assignment->active) {
            $this->bumpSkip($stats, 'inactive');
            $check['reason'] = 'INACTIVE';
            $this->logCheck($check);

            return;
        }

        if (! $jadwal) {
            $this->bumpSkip($stats, 'no_jadwal');
            $check['reason'] = 'NO_JADWAL';
            $this->logCheck($check);

            return;
        }

        if (! $this->isWithinTreatmentPeriod($jadwal, $today)) {
            $this->bumpSkip($stats, 'outside_period');
            $check['reason'] = 'OUTSIDE_DATE_RANGE';
            $this->logCheck($check);

            return;
        }

        $pasien = $assignment->pasien ?? $jadwal->pasien;
        if ($pasien && ($pasien->status !== 'Aktif' || ($pasien->user && $pasien->user->active === false))) {
            $this->bumpSkip($stats, 'inactive_patient');
            $check['reason'] = 'INACTIVE_PATIENT';
            $this->logCheck($check);

            return;
        }

        $pasienId = (int) ($jadwal->pasien_id ?: $assignment->pasien_id);
        $obatId = (int) ($jadwal->obat_id ?: $assignment->obat_id);
        if (! $pasienId || ! $obatId) {
            $reason = $pasienId ? 'missing_obat' : 'missing_patient';
            $this->bumpSkip($stats, $reason);
            $check['reason'] = strtoupper($reason);
            $this->logCheck($check);

            return;
        }

        $details = $jadwal->details;
        if ($details->isEmpty()) {
            $this->bumpSkip($stats, 'no_details');
            $check['reason'] = 'NO_DETAIL';
            $this->logCheck($check);

            return;
        }

        $stats['master_eligible']++;
        $validTimes = [];
        $decisions = [];

        foreach ($details as $detail) {
            $stats['details_total']++;
            $scheduledAt = $this->scheduledAt($today, $detail->waktu, $timezone);
            $scheduledLabel = $scheduledAt?->timezone($timezone)->format('Y-m-d H:i:s');

            if (! $scheduledAt) {
                $this->bumpSkip($stats, 'invalid_time');
                $decisions[] = 'SKIP:INVALID_TIME';
                Log::info('[MEDICATION_GENERATE_SKIPPED]', [
                    'patient_id' => $pasienId,
                    'obat_pasien_id' => $assignment->id,
                    'obat_id' => $obatId,
                    'detail_id' => $detail->id,
                    'reason' => 'INVALID_TIME',
                ]);

                continue;
            }

            $validTimes[] = $scheduledAt->format('Y-m-d H:i:s');

            $existing = HistoryJadwalObatPasien::query()
                ->blocksGeneration()
                ->where('pasien_id', $pasienId)
                ->where('detail_jadwal_obat_id', $detail->id)
                ->where('waktu', $scheduledAt->format('Y-m-d H:i:s'))
                ->first();

            if ($existing && $existing->source === HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED) {
                if ($restoreManualDeletions) {
                    $existing->update([
                        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
                        'sudah_minum' => false,
                        'detail_jadwal_obat_id' => $detail->id,
                        'dosis' => $detail->dosis,
                        'satuan' => $detail->satuan,
                    ]);
                    $stats['history_created']++;
                    $decisions[] = 'RESTORE';
                    Log::info('[MEDICATION_GENERATE_CREATED]', [
                        'history_id' => $existing->id,
                        'patient_id' => $pasienId,
                        'obat_pasien_id' => $assignment->id,
                        'obat_id' => $obatId,
                        'scheduled_at' => $scheduledLabel,
                        'reason' => 'restored_manual_deletion',
                        'user_id' => $userId,
                    ]);

                    continue;
                }

                $stats['history_suppressed']++;
                $this->bumpSkip($stats, 'manually_deleted');
                $decisions[] = 'SKIP:MANUALLY_DELETED';
                Log::info('[MEDICATION_GENERATE_EXISTING]', [
                    'history_id' => $existing->id,
                    'patient_id' => $pasienId,
                    'obat_pasien_id' => $assignment->id,
                    'obat_id' => $obatId,
                    'scheduled_at' => $scheduledLabel,
                    'reason' => 'manually_deleted',
                    'user_id' => $userId,
                ]);
                Log::info('[MEDICATION_GENERATE_SKIPPED]', [
                    'patient_id' => $pasienId,
                    'obat_pasien_id' => $assignment->id,
                    'obat_id' => $obatId,
                    'reason' => 'MANUALLY_DELETED',
                ]);

                continue;
            }

            if ($existing) {
                $stats['history_existing']++;
                $decisions[] = 'EXISTING';
                Log::info('[MEDICATION_GENERATE_EXISTING]', [
                    'history_id' => $existing->id,
                    'patient_id' => $pasienId,
                    'obat_pasien_id' => $assignment->id,
                    'obat_id' => $obatId,
                    'scheduled_at' => $scheduledLabel,
                    'reason' => 'already_exists',
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
            $decisions[] = 'CREATE';
            Log::info('[MEDICATION_GENERATE_CREATED]', [
                'history_id' => $history->id,
                'patient_id' => $pasienId,
                'obat_pasien_id' => $assignment->id,
                'obat_id' => $obatId,
                'scheduled_at' => $scheduledLabel,
                'user_id' => $userId,
            ]);
        }

        $check['decision'] = in_array('CREATE', $decisions, true) || in_array('RESTORE', $decisions, true)
            ? 'CREATE'
            : (in_array('EXISTING', $decisions, true) ? 'EXISTING' : 'SKIP');
        $check['reason'] = implode(',', $decisions) ?: 'NO_DETAIL';
        $this->logCheck($check);

        if ($validTimes !== []) {
            $key = $pasienId.':'.$obatId;
            $pruneTimes[$key] = array_merge($pruneTimes[$key] ?? [], $validTimes);
        }
    }

    /**
     * @param  array<string, mixed>  $check
     */
    private function logCheck(array $check): void
    {
        Log::info('[MEDICATION_GENERATE_CHECK]', $check);
        if (($check['decision'] ?? '') === 'SKIP') {
            Log::info('[MEDICATION_GENERATE_SKIPPED]', [
                'patient_id' => $check['patient_id'] ?? null,
                'obat_pasien_id' => $check['obat_pasien_id'] ?? null,
                'obat_id' => $check['obat_id'] ?? null,
                'reason' => $check['reason'] ?? 'skipped',
            ]);
            $this->diag->info('MED_GENERATOR_SKIP', [
                'obat_pasien_id' => $check['obat_pasien_id'] ?? null,
                'result' => $check['reason'] ?? 'skipped',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function bumpSkip(array &$stats, string $reason): void
    {
        $stats['skipped']++;
        $stats['skip_reasons'][$reason] = (int) ($stats['skip_reasons'][$reason] ?? 0) + 1;
    }

    private function timeLabel(mixed $waktu): ?string
    {
        if ($waktu instanceof \DateTimeInterface) {
            return Carbon::instance($waktu)->format('H:i');
        }
        $time = trim((string) $waktu);
        if ($time === '') {
            return null;
        }

        return substr($time, 0, 5);
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
     * @param  list<string>  $validTimes
     */
    private function forgetStaleUpcomingToday(int $pasienId, int $obatId, string $today, array $validTimes): void
    {
        if ($validTimes === []) {
            return;
        }

        HistoryJadwalObatPasien::query()
            ->clinical()
            ->where('pasien_id', $pasienId)
            ->where('obat_id', $obatId)
            ->whereDate('waktu', $today)
            ->where(function ($q) {
                $q->where('sudah_minum', false)->orWhereNull('sudah_minum');
            })
            ->where('waktu', '>', now())
            ->whereNotIn('waktu', $validTimes)
            ->delete();
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
