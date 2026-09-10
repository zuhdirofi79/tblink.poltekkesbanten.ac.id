<?php

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\UserPushSubscription;
use App\Services\FcmPushService;
use App\Services\ManualMedicationReminderService;
use App\Services\PushDiagnosticLogger;
use App\Services\PushSubscriptionStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function manualReminderWorld(array $overrides = []): array
{
    Permission::findOrCreate('Admin', 'web');
    Permission::findOrCreate('Pasien', 'web');

    $timezone = (string) config('app.timezone');
    $now = Carbon::now($timezone);

    $admin = User::factory()->create();
    $admin->givePermissionTo('Admin');
    $patientUser = User::factory()->create(['username' => $overrides['username'] ?? 'rofite1']);
    $patientUser->givePermissionTo('Pasien');

    $puskesmas = Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => 'PKM Uji',
        'kode_puskesmas' => 'U'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);
    $obat = Obat::create([
        'nama_obat' => $overrides['obat_nama'] ?? 'Rifampisin Uji',
        'deskripsi' => 'R',
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
    $detail = $jadwal->details()->create([
        'waktu' => '08:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

    if ($overrides['with_subscription'] ?? false) {
        $token = 'test-fcm-token-'.$patientUser->id;
        UserPushSubscription::create([
            'user_id' => $patientUser->id,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'platform' => 'android',
            'device_label' => 'test',
            'last_seen_at' => now(),
        ]);
    }

    return compact('admin', 'patientUser', 'puskesmas', 'pasien', 'obat', 'obatPasien', 'jadwal', 'detail');
}

beforeEach(function () {
    Http::fake();
});

test('authorized medication manager can create a today test reminder without sending fcm', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld();

    $this->actingAs($world['admin'])
        ->post(route('pasien.obat.manual-reminder', $world['pasien']), [
            'obat_pasien_id' => $world['obatPasien']->id,
            'waktu' => '21:53',
        ])
        ->assertRedirect(route('pasien.obat.index', $world['pasien']))
        ->assertSessionHas('success');

    $row = HistoryJadwalObatPasien::first();
    expect($row)->not->toBeNull()
        ->and($row->source)->toBe(HistoryJadwalObatPasien::SOURCE_MANUAL_TEST)
        ->and($row->pasien_id)->toBe($world['pasien']->id)
        ->and($row->obat_id)->toBe($world['obat']->id)
        ->and($row->sudah_minum)->toBeFalse()
        ->and($row->waktu->timezone($tz)->format('H:i'))->toBe('21:53')
        ->and($row->hasBeenNotified($world['patientUser']->id))->toBeFalse();
});

test('unauthorized user cannot create a test reminder', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld();

    $this->actingAs($world['patientUser'])
        ->post(route('pasien.obat.manual-reminder', $world['pasien']), [
            'obat_pasien_id' => $world['obatPasien']->id,
            'waktu' => '21:53',
        ])
        ->assertForbidden();

    expect(HistoryJadwalObatPasien::query()->count())->toBe(0);
});

test('selected medication must belong to the selected patient', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld();
    $other = manualReminderWorld(['obat_nama' => 'Obat Lain', 'username' => 'pasienlain']);

    $this->actingAs($world['admin'])
        ->from(route('pasien.obat.index', $world['pasien']))
        ->post(route('pasien.obat.manual-reminder', $world['pasien']), [
            'obat_pasien_id' => $other['obatPasien']->id,
            'waktu' => '21:53',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('obat_pasien_id');
});

test('past time is rejected and future today time is accepted', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld();
    $service = app(ManualMedicationReminderService::class);

    expect(fn () => $service->create($world['pasien'], $world['obatPasien']->id, '21:40', $world['admin']->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $row = $service->create($world['pasien'], $world['obatPasien']->id, '21:53', $world['admin']->id);
    expect($row->waktu->timezone($tz)->format('H:i'))->toBe('21:53');
});

test('test reminder does not alter stock or adherence statistics', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(12, 0, 0));
    $world = manualReminderWorld();

    HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $world['detail']->id,
        'waktu' => Carbon::now($tz)->setTime(8, 0, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => true,
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ]);
    HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $world['detail']->id,
        'waktu' => Carbon::now($tz)->setTime(10, 0, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ]);

    $world['obatPasien']->unsetRelation('jadwal');
    $stockBefore = $world['obatPasien']->fresh()->stock;
    $kepatuhanBefore = $world['pasien']->kepatuhanMinumObat();

    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:53',
        $world['admin']->id,
    );

    $this->travelTo(Carbon::now($tz)->setTime(21, 55, 0));
    $world['obatPasien']->unsetRelation('jadwal');

    expect($world['obatPasien']->fresh()->stock)->toBe($stockBefore)
        ->and($world['pasien']->kepatuhanMinumObat()['minum'])->toBe($kepatuhanBefore['minum'])
        ->and($world['pasien']->kepatuhanMinumObat()['tdk_minum'])->toBe($kepatuhanBefore['tdk_minum']);
});

test('due test reminder calls fcm for the patient not the operator and is idempotent', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld(['with_subscription' => true]);
    $history = app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:53',
        $world['admin']->id,
    );

    $this->travelTo(Carbon::now($tz)->setTime(21, 52, 0));
    $pushEarly = Mockery::mock(FcmPushService::class);
    $pushEarly->shouldReceive('sendToUser')->never();
    $pushEarly->shouldReceive('tokensForUser')->never();
    $pushEarly->shouldReceive('fingerprintsForUser')->never();
    app()->instance(FcmPushService::class, $pushEarly);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $push = Mockery::mock(FcmPushService::class, [
        app(PushSubscriptionStore::class),
        app(PushDiagnosticLogger::class),
    ])->makePartial();
    $push->shouldReceive('send')->once()->withArgs(function ($token, $title, $body, $url, $historyId, $traceId, $type) use ($history, $world) {
        return (int) $historyId === (int) $history->id
            && $type === 'medication_reminder_test'
            && str_contains($title, $world['obat']->nama_obat)
            && str_contains($body, '21:53');
    })->andReturn([
        'success' => true,
        'status' => 200,
        'reason' => 'FCM_ACCEPTED',
        'message_id' => 'projects/test/messages/1',
        'token_fingerprint' => 'abc',
        'delivery' => 'FCM_ACCEPTED',
        'trace_id' => 'trace',
    ]);
    app()->instance(FcmPushService::class, $push);

    $this->travelTo(Carbon::now($tz)->setTime(21, 53, 17));
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $history->refresh();
    expect($history->hasBeenNotified($world['patientUser']->id))->toBeTrue()
        ->and($history->hasBeenNotified($world['admin']->id))->toBeFalse();

    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('fcm failure does not mark a test reminder sent', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld(['with_subscription' => true]);
    $history = app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:53',
        $world['admin']->id,
    );

    $push = Mockery::mock(FcmPushService::class, [
        app(PushSubscriptionStore::class),
        app(PushDiagnosticLogger::class),
    ])->makePartial();
    $push->shouldReceive('send')->once()->andReturn([
        'success' => false,
        'status' => 500,
        'reason' => 'fcm_error',
        'message_id' => null,
        'token_fingerprint' => 'abc',
        'delivery' => 'fcm_error',
        'trace_id' => 'trace',
    ]);
    app()->instance(FcmPushService::class, $push);

    $this->travelTo(Carbon::now($tz)->setTime(21, 53, 0));
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $history->refresh();
    expect($history->hasBeenNotified($world['patientUser']->id))->toBeFalse();
});

test('real clinical histories remain unchanged when a test reminder exists', function () {
    $tz = (string) config('app.timezone');
    $this->travelTo(Carbon::now($tz)->setTime(21, 50, 0));
    $world = manualReminderWorld(['with_subscription' => true]);

    $clinical = HistoryJadwalObatPasien::create([
        'pasien_id' => $world['pasien']->id,
        'obat_id' => $world['obat']->id,
        'detail_jadwal_obat_id' => $world['detail']->id,
        'waktu' => Carbon::now($tz)->setTime(21, 53, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
        'source' => HistoryJadwalObatPasien::SOURCE_SCHEDULE,
    ]);
    $test = app(ManualMedicationReminderService::class)->create(
        $world['pasien'],
        $world['obatPasien']->id,
        '21:55',
        $world['admin']->id,
    );

    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('tokensForUser')->andReturn(['test-token']);
    $push->shouldReceive('fingerprintsForUser')->andReturn(['abc123']);
    $push->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn ($user, $title, $body, $url, $historyId, $traceId, $type) => (int) $historyId === (int) $clinical->id && $type === 'medication_reminder')
        ->andReturn([
            'success' => true,
            'status' => 200,
            'reason' => 'FCM_ACCEPTED',
            'message_id' => '1',
            'token_fingerprint' => 'abc',
            'delivery' => 'FCM_ACCEPTED',
            'trace_id' => 't',
            'subscriptions_found' => 1,
            'subscriptions_sent' => 1,
            'subscriptions_failed' => 0,
        ]);
    app()->instance(FcmPushService::class, $push);

    $this->travelTo(Carbon::now($tz)->setTime(21, 53, 30));
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $clinical->refresh();
    $test->refresh();
    expect($test->hasBeenNotified($world['patientUser']->id))->toBeFalse()
        ->and($test->source)->toBe(HistoryJadwalObatPasien::SOURCE_MANUAL_TEST);
});
