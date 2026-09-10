<?php

namespace App\Services;

use App\Models\HistoryJadwalObatPasien;
use App\Models\ObatPasien;
use App\Models\Pasien;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ManualMedicationReminderService
{
    public function __construct(private PushDiagnosticLogger $diag)
    {
    }

    public function create(Pasien $pasien, int $obatPasienId, string $waktu, int $createdByUserId): HistoryJadwalObatPasien
    {
        $obatPasien = ObatPasien::query()
            ->with(['obat', 'jadwal.details'])
            ->where('id', $obatPasienId)
            ->where('pasien_id', $pasien->id)
            ->first();

        if (! $obatPasien) {
            throw ValidationException::withMessages([
                'obat_pasien_id' => 'Obat tidak terkait dengan pasien ini.',
            ]);
        }

        $detail = $obatPasien->jadwal?->details?->first();
        if (! $detail) {
            throw ValidationException::withMessages([
                'obat_pasien_id' => 'Obat pasien belum memiliki waktu dan dosis.',
            ]);
        }

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $now = now()->timezone($timezone);
        $scheduled = $this->scheduledToday($waktu, $timezone, $now->toDateString());
        if (! $scheduled) {
            throw ValidationException::withMessages([
                'waktu' => 'Format waktu tidak valid.',
            ]);
        }

        if ($scheduled->toDateString() !== $now->toDateString()) {
            throw ValidationException::withMessages([
                'waktu' => 'Jadwal uji hanya berlaku untuk hari ini.',
            ]);
        }

        if ($scheduled->lt($now->copy()->addMinute())) {
            throw ValidationException::withMessages([
                'waktu' => 'Waktu uji harus minimal 1 menit ke depan (hari ini).',
            ]);
        }

        $history = HistoryJadwalObatPasien::create([
            'pasien_id' => $pasien->id,
            'obat_id' => $obatPasien->obat_id,
            'detail_jadwal_obat_id' => $detail->id,
            'waktu' => $scheduled,
            'dosis' => $detail->dosis,
            'satuan' => $detail->satuan,
            'sudah_minum' => false,
            'source' => HistoryJadwalObatPasien::SOURCE_MANUAL_TEST,
        ]);

        $this->diag->info('MANUAL_MED_TEST_CREATED', [
            'history_id' => $history->id,
            'pasien_id' => $pasien->id,
            'obat_id' => $obatPasien->obat_id,
            'scheduled_at' => $scheduled->toDateTimeString(),
            'created_by_user_id' => $createdByUserId,
            'notification_type' => 'medication_reminder_test',
        ]);

        return $history;
    }

    public function cancel(HistoryJadwalObatPasien $history): void
    {
        if (! $history->isManualTest()) {
            throw ValidationException::withMessages([
                'history' => 'Hanya jadwal uji notifikasi yang dapat dibatalkan lewat aksi ini.',
            ]);
        }

        if ($history->hasBeenNotified()) {
            throw ValidationException::withMessages([
                'history' => 'Jadwal uji yang sudah terkirim tidak dapat dibatalkan.',
            ]);
        }

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $scheduled = $history->waktu instanceof Carbon
            ? $history->waktu->copy()->timezone($timezone)
            : Carbon::parse($history->waktu, $timezone);
        if ($scheduled->lte(now()->timezone($timezone))) {
            throw ValidationException::withMessages([
                'history' => 'Jadwal uji yang sudah lewat tidak dapat dibatalkan.',
            ]);
        }

        $history->delete();
    }

    public function scheduledToday(string $waktu, string $timezone, string $date): ?Carbon
    {
        $time = trim($waktu);
        $time = preg_replace('/\..*$/', '', $time) ?? '';
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d H:i:s', $date.' '.$time, $timezone);
        } catch (\Throwable) {
            return null;
        }

        return $parsed ?: null;
    }
}
