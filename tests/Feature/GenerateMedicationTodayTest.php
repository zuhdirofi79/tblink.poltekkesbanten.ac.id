<?php

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ManualMedicationReminderService;
use App\Services\MedicationScheduleGenerator;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function generateTodayWorld(array $overrides = []): array
{
    Permission::findOrCreate('Admin', 'web');
    Permission::findOrCreate('Pasien', 'web');

    $tz = (string) config('app.timezone');
    $now = Carbon::now($tz);

    $admin = User::factory()->create(['username' => 'admin_gen_'.fake()->unique()->numerify('###')]);
    $admin->givePermissionTo('Admin');

    $patientUser = User::factory()->create([
        'username' => $overrides['username'] ?? ('pasien_gen_'.fake()->unique()->numerify('###')),
        'active' => true,
    ]);
    $patientUser->givePermissionTo('Pasien');

    $otherUser = User::factory()->create(['username' => 'other_gen_'.fake()->unique()->numerify('###')]);
    $otherUser->givePermissionTo('Pasien');

    $puskesmas = Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => 'PKM Generate',
        'kode_puskesmas' => 'G'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);

    $obat = Obat::create([
        'nama_obat' => $overrides['obat_nama'] ?? 'Rifampisin',
        'deskripsi' => 'R',
    ]);
    $obatB = Obat::create([
        'nama_obat' => 'Parasetamol',
        'deskripsi' => 'P',
    ]);

    $pasien = Pasien::create([
        'user_id' => $patientUser->id,
        'puskesmas_id' => $puskesmas->id,
        'nik' => '3173'.fake()->unique()->numerify('########'),
        'nama' => 'ROFI TEST',
        'tgl_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Alamat',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => $now->toDateString(),
        'mulai_pengobatan' => $now->toDateString(),
        'selesai_pengobatan' => $now->copy()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);

    $pasienB = Pasien::create([
        'user_id' => $otherUser->id,
        'puskesmas_id' => $puskesmas->id,
        'nik' => '3173'.fake()->unique()->numerify('########'),
        'nama' => 'PASIEN B',
        'tgl_lahir' => '1991-01-01',
        'jenis_kelamin' => 'Perempuan',
        'alamat' => 'Alamat',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => $now->toDateString(),
        'mulai_pengobatan' => $now->toDateString(),
        'selesai_pengobatan' => $now->copy()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);

    $times = $overrides['times'] ?? ['07:00:00'];
    $active = $overrides['active'] ?? true;
    $mulai = $overrides['mulai_tanggal'] ?? $now->toDateString();
    $selesai = $overrides['selesai_tanggal'] ?? $now->copy()->addDays(28)->toDateString();

    $obatPasien = ObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'active' => $active,
    ]);
    $jadwal = JadwalObat::create([
        'obat_pasien_id' => $obatPasien->id,
        'obat_id' => $obat->id,
        'pasien_id' => $pasien->id,
        'mulai_tanggal' => $mulai,
        'selesai_tanggal' => $selesai,
        'qty_diberikan' => 28,
        'satuan' => 'Tablet',
    ]);
    $details = [];
    foreach ($times as $time) {
        $details[] = $jadwal->details()->create([
            'waktu' => $time,
            'dosis' => 1,
            'satuan' => 'Tablet',
        ]);
    }

    $obatPasienB = ObatPasien::create([
        'pasien_id' => $pasienB->id,
        'obat_id' => $obatB->id,
        'active' => true,
    ]);
    $jadwalB = JadwalObat::create([
        'obat_pasien_id' => $obatPasienB->id,
        'obat_id' => $obatB->id,
        'pasien_id' => $pasienB->id,
        'mulai_tanggal' => $now->toDateString(),
        'selesai_tanggal' => $now->copy()->addDays(28)->toDateString(),
        'qty_diberikan' => 10,
        'satuan' => 'Tablet',
    ]);
    $jadwalB->details()->create([
        'waktu' => '09:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

    return compact(
        'admin',
        'pasien',
        'pasienB',
        'obat',
        'obatB',
        'obatPasien',
        'obatPasienB',
        'jadwal',
        'jadwalB',
        'details',
        'patientUser',
    );
}

test('active medication with one schedule time generates one history row', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']))
        ->assertSessionHas('success');

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(1);
});

test('active medication with multiple times generates multiple history rows', function () {
    $world = generateTodayWorld(['times' => ['07:00:00', '14:00:00', '20:00:00']]);

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('success');

    $times = HistoryJadwalObatPasien::clinical()
        ->where('pasien_id', $world['pasien']->id)
        ->get()
        ->map(fn ($row) => $row->waktu->timezone((string) config('app.timezone'))->format('H:i'))
        ->sort()
        ->values()
        ->all();

    expect($times)->toBe(['07:00', '14:00', '20:00'])
        ->and(count($times))->toBe(3);
});

test('inactive medication generates zero histories', function () {
    $world = generateTodayWorld(['active' => false]);

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('error');

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(0);
});

test('medication outside treatment date generates zero histories', function () {
    $world = generateTodayWorld([
        'mulai_tanggal' => now()->addDay()->toDateString(),
        'selesai_tanggal' => now()->addDays(28)->toDateString(),
    ]);

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('error');

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(0);
});

test('generate twice does not create duplicates', function () {
    $world = generateTodayWorld(['times' => ['07:00:00', '14:00:00']]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));
    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('success');

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(2);
});

test('generate for patient A does not generate schedules for patient B', function () {
    $world = generateTodayWorld();

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('success');

    expect(HistoryJadwalObatPasien::where('pasien_id', $world['pasien']->id)->count())->toBe(1)
        ->and(HistoryJadwalObatPasien::where('pasien_id', $world['pasienB']->id)->count())->toBe(0);
});

test('existing history with sudah minum true is not reset', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $tz = (string) config('app.timezone');
    $history = HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $world['details'][0]->id,
        'waktu' => Carbon::now($tz)->setTime(7, 0, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => true,
        'waktu_aktual_minum' => Carbon::now($tz)->setTime(7, 5, 0),
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $history->refresh();
    expect($history->sudah_minum)->toBeTrue()
        ->and(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(1);
});

test('existing history with notifikasi dikirim is not reset', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $tz = (string) config('app.timezone');
    $sent = [['user_id' => $world['patientUser']->id, 'at' => now()->toIso8601String()]];
    $history = HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $world['details'][0]->id,
        'waktu' => Carbon::now($tz)->setTime(7, 0, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
        'notifikasi_dikirim' => $sent,
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $history->refresh();
    expect($history->hasBeenNotified($world['patientUser']->id))->toBeTrue()
        ->and($history->notifikasi_dikirim)->toBe($sent);
});

test('manual test schedule remains intact after generate', function () {
    $this->travelTo(Carbon::now((string) config('app.timezone'))->setTime(21, 50, 0));
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $test = app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:53',
        $world['admin']->id,
    );

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    expect(HistoryJadwalObatPasien::find($test->id))->not->toBeNull()
        ->and($test->fresh()->source)->toBe(HistoryJadwalObatPasien::SOURCE_MANUAL_TEST)
        ->and(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(1);
});

test('two active assignments of the same medicine both generate their own history', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $secondOp = ObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'active' => true,
    ]);
    $secondJadwal = JadwalObat::create([
        'obat_pasien_id' => $secondOp->id,
        'obat_id' => $world['obat']->id,
        'pasien_id' => $world['pasien']->id,
        'mulai_tanggal' => now()->toDateString(),
        'selesai_tanggal' => now()->addDays(28)->toDateString(),
        'qty_diberikan' => 7,
        'satuan' => 'Tablet',
    ]);
    $secondJadwal->details()->create([
        'waktu' => '07:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(2);
});

test('generated history contains the selected patient id', function () {
    $world = generateTodayWorld();

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $row = HistoryJadwalObatPasien::clinical()->first();
    expect($row->pasien_id)->toBe($world['pasien']->id);
});

test('generated history contains the correct obat id', function () {
    $world = generateTodayWorld();

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $row = HistoryJadwalObatPasien::clinical()->first();
    expect($row->obat_id)->toBe($world['obat']->id)
        ->and($row->obat_id)->not->toBe($world['obatB']->id);
});

test('generated history contains exact scheduled datetime in asia jakarta', function () {
    $world = generateTodayWorld(['times' => ['14:30:00']]);
    $tz = (string) config('app.timezone');

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $row = HistoryJadwalObatPasien::clinical()->first();
    expect($tz)->toBe('Asia/Jakarta')
        ->and($row->waktu->timezone($tz)->format('Y-m-d H:i'))->toBe(Carbon::now($tz)->toDateString().' 14:30')
        ->and($row->source)->toBe(HistoryJadwalObatPasien::SOURCE_SCHEDULE)
        ->and($row->sudah_minum)->toBeFalse();
});

test('generate controller uses the same service as automatic generation', function () {
    $world = generateTodayWorld(['times' => ['08:15:00']]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));
    $fromButton = HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->get(['pasien_id', 'obat_id', 'waktu', 'source'])->toArray();

    HistoryJadwalObatPasien::query()->delete();

    $this->artisan('app:generate-jadwal-minum-obat')->assertSuccessful();
    $fromCron = HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->get(['pasien_id', 'obat_id', 'waktu', 'source'])->toArray();

    expect($fromButton)->toBe($fromCron)
        ->and(app(MedicationScheduleGenerator::class))->toBeInstanceOf(MedicationScheduleGenerator::class);
});

test('generate page lists created rows for the selected patient', function () {
    $world = generateTodayWorld(['times' => ['07:00:00', '14:00:00']]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $this->actingAs($world['admin'])
        ->get(route('pasien.obat.index', $world['pasien']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/obat/index')
            ->has('obatHariIni', 2)
            ->where('obatHariIni.0.pasien_id', $world['pasien']->id)
            ->where('obatHariIni.1.pasien_id', $world['pasien']->id)
        );
});

test('multiple active medications all appear after generate', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $secondOp = ObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obatB']->id,
        'active' => true,
    ]);
    $secondJadwal = JadwalObat::create([
        'obat_pasien_id' => $secondOp->id,
        'obat_id' => $world['obatB']->id,
        'pasien_id' => $world['pasien']->id,
        'mulai_tanggal' => now()->toDateString(),
        'selesai_tanggal' => now()->addDays(28)->toDateString(),
        'qty_diberikan' => 10,
        'satuan' => 'Tablet',
    ]);
    $secondJadwal->details()->create(['waktu' => '22:08:00', 'dosis' => 1, 'satuan' => 'Tablet']);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    $obatIds = HistoryJadwalObatPasien::clinical()
        ->where('pasien_id', $world['pasien']->id)
        ->pluck('obat_id')
        ->sort()
        ->values()
        ->all();

    expect($obatIds)->toBe(collect([$world['obat']->id, $world['obatB']->id])->sort()->values()->all());

    $this->actingAs($world['admin'])
        ->get(route('pasien.obat.index', $world['pasien']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('obatHariIni', 2));
});

test('explicit generate restores a manually deleted today history', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));
    $row = HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->first();
    $waktu = $row->waktu;
    $detailId = $row->detail_jadwal_obat_id;
    $row->delete();
    HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $detailId,
        'waktu' => $waktu,
        'dosis' => 1,
        'satuan' => 'Tablet',
        'source' => HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED,
    ]);

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.generate', $world['pasien']))
        ->assertSessionHas('success');

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(1)
        ->and(HistoryJadwalObatPasien::query()->where('source', HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED)->count())->toBe(0);
});

test('automatic generate does not restore a manually deleted history', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $world['pasien']->id);
    $row = HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->first();
    $waktu = $row->waktu;
    $detailId = $row->detail_jadwal_obat_id;
    $row->delete();
    HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $detailId,
        'waktu' => $waktu,
        'dosis' => 1,
        'satuan' => 'Tablet',
        'source' => HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED,
    ]);

    $stats = app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $world['pasien']->id);

    expect($stats['history_created'])->toBe(0)
        ->and($stats['skip_reasons']['manually_deleted'] ?? 0)->toBe(1)
        ->and(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(0);
});

test('one invalid medication does not stop valid medications', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $broken = ObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obatB']->id,
        'active' => true,
    ]);
    JadwalObat::create([
        'obat_pasien_id' => $broken->id,
        'obat_id' => $world['obatB']->id,
        'pasien_id' => $world['pasien']->id,
        'mulai_tanggal' => now()->toDateString(),
        'selesai_tanggal' => now()->addDays(28)->toDateString(),
        'qty_diberikan' => 5,
        'satuan' => 'Tablet',
    ]);

    $this->actingAs($world['admin'])->post(route('pasien.obat.generate', $world['pasien']));

    expect(HistoryJadwalObatPasien::clinical()->where('pasien_id', $world['pasien']->id)->count())->toBe(1)
        ->and(HistoryJadwalObatPasien::clinical()->where('obat_id', $world['obat']->id)->count())->toBe(1)
        ->and(HistoryJadwalObatPasien::clinical()->where('obat_id', $world['obatB']->id)->count())->toBe(0);
});

test('empty schedule details produce an explicit skip reason', function () {
    $world = generateTodayWorld(['times' => ['07:00:00']]);
    $world['jadwal']->details()->delete();

    $stats = app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $world['pasien']->id);

    expect($stats['history_created'])->toBe(0)
        ->and($stats['skip_reasons']['no_details'] ?? 0)->toBe(1);
});
