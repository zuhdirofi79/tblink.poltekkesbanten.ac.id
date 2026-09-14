<?php

use App\Models\DetailJadwalObat;
use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ManualMedicationReminderService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function deleteHistoryPermissions(): void
{
    Permission::findOrCreate('Admin', 'web');
    Permission::findOrCreate('Superadmin', 'web');
    Permission::findOrCreate('Pasien', 'web');
    Permission::findOrCreate('Pendamping', 'web');
}

function deleteHistoryWorld(): array
{
    deleteHistoryPermissions();

    $admin = User::factory()->create(['username' => 'admin_del_hist']);
    $admin->givePermissionTo('Admin');

    $adminB = User::factory()->create(['username' => 'admin_del_hist_b']);
    $adminB->givePermissionTo('Admin');

    $superadmin = User::factory()->create(['username' => 'sa_del_hist']);
    $superadmin->givePermissionTo('Superadmin');

    $patientUser = User::factory()->create(['username' => 'rofi_del_hist']);
    $patientUser->givePermissionTo('Pasien');

    $otherPatientUser = User::factory()->create(['username' => 'other_del_hist']);
    $otherPatientUser->givePermissionTo('Pasien');

    $pendamping = User::factory()->create(['username' => 'pend_del_hist']);
    $pendamping->givePermissionTo('Pendamping');

    $puskesmas = Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => 'PKM Delete Hist',
        'kode_puskesmas' => 'D'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);
    $puskesmasB = Puskesmas::create([
        'user_id' => $adminB->id,
        'nama_puskesmas' => 'PKM Delete Hist B',
        'kode_puskesmas' => 'E'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);

    $obat = Obat::create([
        'nama_obat' => 'Rifampisin',
        'deskripsi' => 'R',
    ]);
    $obatB = Obat::create([
        'nama_obat' => 'Pirazinamid',
        'deskripsi' => 'Z',
    ]);

    $now = Carbon::now((string) config('app.timezone'));

    $pasien = Pasien::create([
        'user_id' => $patientUser->id,
        'puskesmas_id' => $puskesmas->id,
        'pendamping_id' => $pendamping->id,
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
        'user_id' => $otherPatientUser->id,
        'puskesmas_id' => $puskesmasB->id,
        'nik' => '3173'.fake()->unique()->numerify('########'),
        'nama' => 'PASIEN LAIN',
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

    $obatPasien = ObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'active' => true,
    ]);
    $jadwal = JadwalObat::create([
        'obat_pasien_id' => $obatPasien->id,
        'obat_id' => $obat->id,
        'pasien_id' => $pasien->id,
        'mulai_tanggal' => $now->toDateString(),
        'selesai_tanggal' => $now->copy()->addDays(28)->toDateString(),
        'qty_diberikan' => 28,
        'satuan' => 'Tablet',
    ]);
    $detail0700 = $jadwal->details()->create([
        'waktu' => '07:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);
    $detail1400 = $jadwal->details()->create([
        'waktu' => '14:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

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
        'qty_diberikan' => 14,
        'satuan' => 'Tablet',
    ]);
    $detailB = $jadwalB->details()->create([
        'waktu' => '23:13:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

    return compact(
        'admin',
        'adminB',
        'superadmin',
        'patientUser',
        'pendamping',
        'pasien',
        'pasienB',
        'obat',
        'obatB',
        'obatPasien',
        'obatPasienB',
        'jadwal',
        'jadwalB',
        'detail0700',
        'detail1400',
        'detailB',
    );
}

function makeHistoryRow(Pasien $pasien, Obat $obat, DetailJadwalObat $detail, string $time, array $overrides = []): HistoryJadwalObatPasien
{
    $tz = (string) config('app.timezone');
    [$hour, $minute] = array_map('intval', explode(':', $time));

    return HistoryJadwalObatPasien::create(array_merge([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'detail_jadwal_obat_id' => $detail->id,
        'waktu' => Carbon::now($tz)->setTime($hour, $minute, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ], $overrides));
}

test('authorized admin can delete one history row', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']))
        ->assertSessionHas('success');

    expect(HistoryJadwalObatPasien::find($history->id))->toBeNull();
});

test('deleting one history row does not delete another history row', function () {
    $world = deleteHistoryWorld();
    $keep = makeHistoryRow($world['pasien'], $world['obat'], $world['detail1400'], '14:00');
    $remove = makeHistoryRow($world['pasien'], $world['obatB'], $world['detail0700'], '23:13');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $remove->id]))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']));

    expect(HistoryJadwalObatPasien::find($remove->id))->toBeNull()
        ->and(HistoryJadwalObatPasien::find($keep->id))->not->toBeNull();
});

test('deleting one history row does not delete ObatPasien', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertRedirect();

    expect(ObatPasien::find($world['obatPasien']->id))->not->toBeNull();
});

test('deleting one history row does not delete JadwalObat', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertRedirect();

    expect(JadwalObat::find($world['jadwal']->id))->not->toBeNull();
});

test('deleting one history row does not delete DetailJadwalObat', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertRedirect();

    expect(DetailJadwalObat::find($world['detail0700']->id))->not->toBeNull()
        ->and(DetailJadwalObat::find($world['detail1400']->id))->not->toBeNull();
});

test('user cannot delete another patients history by changing url ids', function () {
    $world = deleteHistoryWorld();
    $foreign = makeHistoryRow($world['pasienB'], $world['obatB'], $world['detailB'], '23:13');

    $this->actingAs($world['superadmin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $foreign->id]))
        ->assertForbidden();

    expect(HistoryJadwalObatPasien::find($foreign->id))->not->toBeNull();
});

test('non authorized patient and pendamping cannot perform admin delete', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');

    $this->actingAs($world['patientUser'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertForbidden();

    $this->actingAs($world['pendamping'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertForbidden();

    expect(HistoryJadwalObatPasien::find($history->id))->not->toBeNull();
});

test('deleting a nonexistent history returns 404', function () {
    $world = deleteHistoryWorld();

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, 999999]))
        ->assertNotFound();
});

test('two identical medicines at different times remain independently deletable', function () {
    $world = deleteHistoryWorld();
    $morning = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');
    $afternoon = makeHistoryRow($world['pasien'], $world['obat'], $world['detail1400'], '14:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $morning->id]))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']));

    expect(HistoryJadwalObatPasien::find($morning->id))->toBeNull()
        ->and(HistoryJadwalObatPasien::find($afternoon->id))->not->toBeNull()
        ->and(HistoryJadwalObatPasien::find($afternoon->id)->waktu->timezone((string) config('app.timezone'))->format('H:i'))->toBe('14:00');
});

test('manual notification test schedule is independently deletable', function () {
    $this->travelTo(Carbon::now((string) config('app.timezone'))->setTime(21, 50, 0));
    $world = deleteHistoryWorld();
    $clinical = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');
    $test = app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:53',
        $world['admin']->id,
    );

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $test->id]))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']));

    expect(HistoryJadwalObatPasien::find($test->id))->toBeNull()
        ->and(HistoryJadwalObatPasien::find($clinical->id))->not->toBeNull()
        ->and(ObatPasien::find($world['obatPasien']->id))->not->toBeNull()
        ->and(JadwalObat::find($world['jadwal']->id))->not->toBeNull();
});

test('obat hari ini page sends history primary keys not indexes or medicine ids', function () {
    $world = deleteHistoryWorld();
    $morning = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');
    $afternoon = makeHistoryRow($world['pasien'], $world['obat'], $world['detail1400'], '14:00');

    $this->actingAs($world['admin'])
        ->get(route('pasien.obat.index', $world['pasien']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/obat/index')
            ->has('obatHariIni', 2)
            ->where('obatHariIni.0.id', $morning->id)
            ->where('obatHariIni.1.id', $afternoon->id)
            ->where('obatHariIni.0.obat_id', $world['obat']->id)
            ->where('obatHariIni.1.obat_id', $world['obat']->id)
        );

    expect($morning->id)->not->toBe($afternoon->id)
        ->and($morning->obat_id)->toBe($afternoon->obat_id)
        ->and($morning->id)->not->toBe(0)
        ->and($afternoon->id)->not->toBe(0);
});

test('legacy hist url still deletes the exact history instance', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');
    $keep = makeHistoryRow($world['pasien'], $world['obat'], $world['detail1400'], '14:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete.compat', $history->id))
        ->assertRedirect(route('pasien.obat.index', $world['pasien']));

    expect(HistoryJadwalObatPasien::find($history->id))->toBeNull()
        ->and(HistoryJadwalObatPasien::find($keep->id))->not->toBeNull();
});

test('manually deleted scheduled history is hidden from today board', function () {
    $world = deleteHistoryWorld();
    $history = makeHistoryRow($world['pasien'], $world['obat'], $world['detail0700'], '07:00');
    $keep = makeHistoryRow($world['pasien'], $world['obat'], $world['detail1400'], '14:00');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete', [$world['pasien']->id, $history->id]))
        ->assertRedirect();

    $this->actingAs($world['admin'])
        ->get(route('pasien.obat.index', $world['pasien']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('obatHariIni', 1)
            ->where('obatHariIni.0.id', $keep->id)
        );

    expect(HistoryJadwalObatPasien::query()->where('source', HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED)->count())->toBe(1);
});

test('puskesmas admin cannot delete history of a patient from another puskesmas', function () {
    $world = deleteHistoryWorld();
    $foreign = makeHistoryRow($world['pasienB'], $world['obatB'], $world['detailB'], '23:13');

    $this->actingAs($world['admin'])
        ->delete(route('pasien.obat.hist.delete.compat', $foreign->id))
        ->assertForbidden();

    expect(HistoryJadwalObatPasien::find($foreign->id))->not->toBeNull();
});
