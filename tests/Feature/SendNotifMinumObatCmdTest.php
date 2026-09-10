<?php

use App\Models\HistoryJadwalObatPasien;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\UserPushSubscription;
use App\Services\FcmPushService;
use App\Services\PushDiagnosticLogger;
use App\Services\PushSubscriptionStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function reminderWorld(array $overrides = []): array
{
    $timezone = (string) config('app.timezone');
    $now = Carbon::now($timezone);
    $scheduled = $overrides['scheduled_at'] ?? $now->copy()->setTime(14, 53, 0);

    $patientUser = User::factory()->create(['username' => $overrides['username'] ?? 'rofite1']);
    $admin = User::factory()->create();
    $puskesmas = Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => $overrides['puskesmas'] ?? 'PKM Test',
        'kode_puskesmas' => 'T001',
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);
    $obat = Obat::create([
        'nama_obat' => $overrides['obat_nama'] ?? 'Obat Uji',
        'deskripsi' => 'unit',
    ]);
    $pendamping = null;
    if ($overrides['with_companion'] ?? false) {
        $pendamping = User::factory()->create();
    }
    $pasien = Pasien::create([
        'user_id' => $patientUser->id,
        'puskesmas_id' => $puskesmas->id,
        'pendamping_id' => $pendamping?->id,
        'nik' => '3173'.fake()->unique()->numerify('########'),
        'nama' => 'Pasien Reminder',
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

    $history = HistoryJadwalObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'detail_jadwal_obat_id' => $overrides['detail_id'] ?? 1,
        'waktu' => $scheduled,
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => $overrides['sudah_minum'] ?? false,
        'notifikasi_dikirim' => $overrides['notifikasi_dikirim'] ?? [],
    ]);

    if ($overrides['with_subscription'] ?? true) {
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

    return compact('patientUser', 'pasien', 'obat', 'history', 'pendamping', 'puskesmas');
}

function fakeAcceptedSend(): array
{
    return [
        'success' => true,
        'status' => 200,
        'reason' => 'FCM_ACCEPTED',
        'message_id' => 'projects/test/messages/1',
        'token_fingerprint' => 'abc123',
        'delivery' => 'FCM_ACCEPTED',
        'trace_id' => 'trace',
        'subscriptions_found' => 1,
        'subscriptions_sent' => 1,
        'subscriptions_failed' => 0,
    ];
}

function bindMockedPush(bool $expectSend = true): \Mockery\MockInterface
{
    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('tokensForUser')->andReturn(['test-token'])->byDefault();
    $push->shouldReceive('fingerprintsForUser')->andReturn(['abc123'])->byDefault();
    if ($expectSend) {
        $push->shouldReceive('sendToUser')->once()->andReturn(fakeAcceptedSend());
    } else {
        $push->shouldReceive('sendToUser')->never();
    }
    app()->instance(FcmPushService::class, $push);

    return $push;
}

beforeEach(function () {
    Http::fake();
});

test('occurrence becomes eligible at scheduled time but not one second before', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    $world = reminderWorld(['scheduled_at' => $scheduled]);

    $this->travelTo($scheduled->copy()->subSecond());
    bindMockedPush(false);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $this->travelTo($scheduled->copy());
    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('tokensForUser')->andReturn(['test-token']);
    $push->shouldReceive('fingerprintsForUser')->andReturn(['abc123']);
    $push->shouldReceive('sendToUser')
        ->once()
        ->withArgs(function ($user, $title, $body, $url, $historyId, $traceId, $type) use ($world) {
            return (int) $user->id === (int) $world['patientUser']->id
                && $type === 'medication_reminder'
                && (int) $historyId === (int) $world['history']->id
                && str_starts_with($title, 'Pengingat Minum Obat')
                && str_contains($body, '14:53')
                && $url === route('dashboard');
        })
        ->andReturn(fakeAcceptedSend());
    app()->instance(FcmPushService::class, $push);

    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('future occurrence is not eligible', function () {
    $timezone = (string) config('app.timezone');
    reminderWorld([
        'scheduled_at' => Carbon::now($timezone)->setTime(14, 53, 0),
    ]);

    $this->travelTo(Carbon::now($timezone)->setTime(14, 50, 0));
    bindMockedPush(false);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('already taken occurrence is not sent', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    reminderWorld([
        'scheduled_at' => $scheduled,
        'sudah_minum' => true,
    ]);

    $this->travelTo($scheduled->copy()->addMinute());
    bindMockedPush(false);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('already notified occurrence is not sent twice', function () {
    $timezone = (string) config('app.timezone');
    $world = reminderWorld([
        'scheduled_at' => Carbon::now($timezone)->setTime(14, 53, 0),
    ]);
    $world['history']->update([
        'notifikasi_dikirim' => [
            ['user_id' => $world['patientUser']->id, 'at' => now()->toIso8601String()],
        ],
    ]);

    $this->travelTo(Carbon::now($timezone)->setTime(14, 54, 0));
    bindMockedPush(false);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('no subscription does not mark notification sent', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    $world = reminderWorld([
        'scheduled_at' => $scheduled,
        'with_subscription' => false,
    ]);

    $this->travelTo($scheduled->copy()->addMinute());
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $world['history']->refresh();
    expect($world['history']->hasBeenNotified($world['patientUser']->id))->toBeFalse();
});

test('fcm failure does not mark notification sent', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    $world = reminderWorld(['scheduled_at' => $scheduled]);

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

    $this->travelTo($scheduled->copy()->addMinute());
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $world['history']->refresh();
    expect($world['history']->hasBeenNotified($world['patientUser']->id))->toBeFalse();
});

test('successful fcm acceptance records per-user idempotency', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    $world = reminderWorld(['scheduled_at' => $scheduled]);

    $push = Mockery::mock(FcmPushService::class, [
        app(PushSubscriptionStore::class),
        app(PushDiagnosticLogger::class),
    ])->makePartial();
    $push->shouldReceive('send')->once()->andReturn([
        'success' => true,
        'status' => 200,
        'reason' => 'FCM_ACCEPTED',
        'message_id' => 'projects/test/messages/1',
        'token_fingerprint' => 'abc',
        'delivery' => 'FCM_ACCEPTED',
        'trace_id' => 'trace',
    ]);
    app()->instance(FcmPushService::class, $push);

    $this->travelTo($scheduled->copy()->addMinute());
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $world['history']->refresh();
    expect($world['history']->hasBeenNotified($world['patientUser']->id))->toBeTrue();

    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('two medications at different times remain independent', function () {
    $timezone = (string) config('app.timezone');
    $firstAt = Carbon::now($timezone)->setTime(14, 53, 0);
    $secondAt = Carbon::now($timezone)->setTime(14, 55, 0);
    $first = reminderWorld(['scheduled_at' => $firstAt, 'obat_nama' => 'Obat A', 'detail_id' => 1]);
    $second = reminderWorld(['scheduled_at' => $secondAt, 'obat_nama' => 'Obat B', 'detail_id' => 2, 'username' => 'rofite1b']);

    $this->travelTo($firstAt->copy()->addMinute());
    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('tokensForUser')->andReturn(['test-token']);
    $push->shouldReceive('fingerprintsForUser')->andReturn(['abc123']);
    $push->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn ($user, $title, $body, $url, $historyId) => (int) $historyId === (int) $first['history']->id)
        ->andReturn(fakeAcceptedSend());
    app()->instance(FcmPushService::class, $push);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $first['history']->update([
        'notifikasi_dikirim' => [
            ['user_id' => $first['patientUser']->id, 'at' => now()->toIso8601String()],
        ],
    ]);

    $this->travelTo($secondAt->copy());
    $push2 = Mockery::mock(FcmPushService::class);
    $push2->shouldReceive('tokensForUser')->andReturn(['test-token']);
    $push2->shouldReceive('fingerprintsForUser')->andReturn(['abc123']);
    $push2->shouldReceive('sendToUser')
        ->once()
        ->withArgs(fn ($user, $title, $body, $url, $historyId) => (int) $historyId === (int) $second['history']->id)
        ->andReturn(fakeAcceptedSend());
    app()->instance(FcmPushService::class, $push2);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});

test('companion failure does not mark companion notified when patient succeeds', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    $world = reminderWorld([
        'scheduled_at' => $scheduled,
        'with_companion' => true,
    ]);

    $push = Mockery::mock(FcmPushService::class, [
        app(PushSubscriptionStore::class),
        app(PushDiagnosticLogger::class),
    ])->makePartial();
    $push->shouldReceive('send')->once()->andReturn([
        'success' => true,
        'status' => 200,
        'reason' => 'FCM_ACCEPTED',
        'message_id' => 'projects/test/messages/1',
        'token_fingerprint' => 'abc',
        'delivery' => 'FCM_ACCEPTED',
        'trace_id' => 'trace',
    ]);
    app()->instance(FcmPushService::class, $push);

    $this->travelTo($scheduled->copy()->addMinute());
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    $world['history']->refresh();
    expect($world['history']->hasBeenNotified($world['patientUser']->id))->toBeTrue()
        ->and($world['history']->hasBeenNotified($world['pendamping']->id))->toBeFalse();
});

test('patient can mark one occurrence taken without affecting another time', function () {
    Permission::findOrCreate('Pasien', 'web');
    $timezone = (string) config('app.timezone');
    $first = reminderWorld([
        'scheduled_at' => Carbon::now($timezone)->setTime(14, 53, 0),
        'with_subscription' => false,
    ]);
    $first['patientUser']->givePermissionTo('Pasien');

    $second = HistoryJadwalObatPasien::create([
        'pasien_id' => $first['pasien']->id,
        'obat_id' => $first['obat']->id,
        'detail_jadwal_obat_id' => 2,
        'waktu' => Carbon::now($timezone)->setTime(14, 55, 0),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
        'notifikasi_dikirim' => [],
    ]);

    $this->actingAs($first['patientUser'])
        ->from('/dashboard')
        ->post(route('ds-data.pasien.sudah-minum-obat', $first['history']))
        ->assertRedirect('/dashboard');

    $first['history']->refresh();
    $second->refresh();

    expect($first['history']->sudah_minum)->toBeTrue()
        ->and($second->sudah_minum)->toBeFalse();
});

test('delayed cron still sends within 60 minutes and not after', function () {
    $timezone = (string) config('app.timezone');
    $scheduled = Carbon::now($timezone)->setTime(14, 53, 0);
    reminderWorld(['scheduled_at' => $scheduled]);

    $this->travelTo($scheduled->copy()->addMinutes(45));
    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('tokensForUser')->andReturn(['test-token']);
    $push->shouldReceive('fingerprintsForUser')->andReturn(['abc123']);
    $push->shouldReceive('sendToUser')->once()->andReturn(fakeAcceptedSend());
    app()->instance(FcmPushService::class, $push);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();

    reminderWorld(['scheduled_at' => $scheduled, 'username' => 'rofite2']);
    $this->travelTo($scheduled->copy()->addMinutes(61));
    bindMockedPush(false);
    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
});
