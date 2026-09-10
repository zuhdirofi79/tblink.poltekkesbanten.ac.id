<?php

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\MedicationScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function medicationFixture(array $overrides = []): array
{
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $puskesmas = Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => 'PKM Test',
        'kode_puskesmas' => 'T001',
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);
    $obat = Obat::create([
        'nama_obat' => 'Rifampisin Test',
        'deskripsi' => 'unit',
    ]);
    $pasien = Pasien::create([
        'user_id' => $user->id,
        'puskesmas_id' => $puskesmas->id,
        'nik' => '3173'.fake()->unique()->numerify('########'),
        'nama' => 'Pasien Generator',
        'tgl_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Alamat',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => now()->toDateString(),
        'mulai_pengobatan' => now()->toDateString(),
        'selesai_pengobatan' => now()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);
    $obatPasien = ObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'active' => $overrides['active'] ?? true,
    ]);
    $jadwal = JadwalObat::create([
        'obat_pasien_id' => $obatPasien->id,
        'obat_id' => $obat->id,
        'pasien_id' => $pasien->id,
        'mulai_tanggal' => $overrides['mulai_tanggal'] ?? now()->toDateString(),
        'selesai_tanggal' => $overrides['selesai_tanggal'] ?? now()->addDays(28)->toDateString(),
        'qty_diberikan' => 28,
        'satuan' => 'Tablet',
    ]);

    if (($overrides['with_detail'] ?? true) === true) {
        $jadwal->details()->create([
            'waktu' => $overrides['waktu'] ?? '17:00:00',
            'dosis' => 1,
            'satuan' => 'Tablet',
        ]);
    }

    return compact('pasien', 'obat', 'jadwal', 'obatPasien');
}

test('generator creates today history for eligible jadwal obat', function () {
    $fixture = medicationFixture();

    $stats = app(MedicationScheduleGenerator::class)->generateForDate();

    expect($stats['history_created'])->toBe(1)
        ->and($stats['errors'])->toBe(0);

    expect(HistoryJadwalObatPasien::query()->count())->toBe(1);

    $row = HistoryJadwalObatPasien::first();
    expect($row->pasien_id)->toBe($fixture['pasien']->id)
        ->and($row->obat_id)->toBe($fixture['obat']->id)
        ->and($row->waktu->timezone(config('app.timezone'))->format('H:i'))->toBe('17:00');
});

test('generator is idempotent for the same day', function () {
    medicationFixture();
    $generator = app(MedicationScheduleGenerator::class);

    $first = $generator->generateForDate();
    $second = $generator->generateForDate();

    expect($first['history_created'])->toBe(1)
        ->and($second['history_created'])->toBe(0)
        ->and($second['history_existing'])->toBe(1)
        ->and(HistoryJadwalObatPasien::query()->count())->toBe(1);
});

test('generator skips inactive obat pasien', function () {
    medicationFixture(['active' => false]);

    $stats = app(MedicationScheduleGenerator::class)->generateForDate();

    expect($stats['history_created'])->toBe(0)
        ->and(HistoryJadwalObatPasien::query()->count())->toBe(0);
});

test('generator skips jadwal without details', function () {
    medicationFixture(['with_detail' => false]);

    $stats = app(MedicationScheduleGenerator::class)->generateForDate();

    expect($stats['history_created'])->toBe(0)
        ->and($stats['skipped'])->toBeGreaterThan(0)
        ->and(HistoryJadwalObatPasien::query()->count())->toBe(0);
});

test('generator skips schedules outside the date range', function () {
    medicationFixture([
        'mulai_tanggal' => now()->addDay()->toDateString(),
        'selesai_tanggal' => now()->addDays(28)->toDateString(),
    ]);

    $stats = app(MedicationScheduleGenerator::class)->generateForDate();

    expect($stats['master_eligible'])->toBe(0)
        ->and($stats['history_created'])->toBe(0);
});

test('history hasBeenNotified is tracked per user', function () {
    $history = new HistoryJadwalObatPasien([
        'notifikasi_dikirim' => [
            ['user_id' => 11, 'at' => now()->toIso8601String()],
        ],
    ]);

    expect($history->hasBeenNotified(11))->toBeTrue()
        ->and($history->hasBeenNotified(22))->toBeFalse();
});
