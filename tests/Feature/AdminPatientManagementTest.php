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
use App\Services\MedicationScheduleGenerator;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function patientManagementPermissions(): void
{
    Permission::findOrCreate('Admin', 'web');
    Permission::findOrCreate('Superadmin', 'web');
    Permission::findOrCreate('Pasien', 'web');
    Permission::findOrCreate('Pendamping', 'web');
    Permission::findOrCreate('User Management', 'web');
}

function makePuskesmasFor(User $admin, string $nama): Puskesmas
{
    return Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => $nama,
        'kode_puskesmas' => 'T'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);
}

function makePatientAt(Puskesmas $puskesmas, array $overrides = []): array
{
    $patientUser = User::factory()->create([
        'username' => $overrides['username'] ?? fake()->unique()->userName(),
        'active' => true,
    ]);
    $patientUser->givePermissionTo('Pasien');

    $pendamping = null;
    if ($overrides['with_pendamping'] ?? true) {
        $pendamping = User::factory()->create();
        $pendamping->givePermissionTo('Pendamping');
    }

    $pasien = Pasien::create([
        'user_id' => $patientUser->id,
        'puskesmas_id' => $puskesmas->id,
        'pendamping_id' => $pendamping?->id,
        'nik' => $overrides['nik'] ?? '3173'.fake()->unique()->numerify('########'),
        'nama' => $overrides['nama'] ?? 'Pasien Uji',
        'tgl_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Alamat',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => now()->toDateString(),
        'mulai_pengobatan' => now()->toDateString(),
        'selesai_pengobatan' => now()->copy()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => $overrides['status'] ?? 'Aktif',
    ]);

    return compact('patientUser', 'pasien', 'pendamping');
}

function patientManagementWorld(): array
{
    patientManagementPermissions();

    $superadmin = User::factory()->create(['username' => 'superadmin_manager']);
    $superadmin->givePermissionTo('Superadmin');

    $puskesmasAdminA = User::factory()->create(['username' => 'pkm_alpha']);
    $puskesmasAdminA->givePermissionTo('Admin');
    $puskesmasA = makePuskesmasFor($puskesmasAdminA, 'PKM Alpha');

    $puskesmasAdminB = User::factory()->create(['username' => 'pkm_beta']);
    $puskesmasAdminB->givePermissionTo('Admin');
    $puskesmasB = makePuskesmasFor($puskesmasAdminB, 'PKM Beta');

    $patientA = makePatientAt($puskesmasA, ['nama' => 'Pasien Alpha', 'username' => 'alpha_pasien']);
    $patientB = makePatientAt($puskesmasB, ['nama' => 'Pasien Beta', 'username' => 'beta_pasien']);

    $plainPatient = User::factory()->create(['username' => 'plain_pasien']);
    $plainPatient->givePermissionTo('Pasien');

    $pendamping = User::factory()->create(['username' => 'plain_pendamping']);
    $pendamping->givePermissionTo('Pendamping');

    return [
        'superadmin' => $superadmin,
        'puskesmasAdminA' => $puskesmasAdminA,
        'puskesmasAdminB' => $puskesmasAdminB,
        'puskesmasA' => $puskesmasA,
        'puskesmasB' => $puskesmasB,
        'pasienA' => $patientA['pasien'],
        'patientUserA' => $patientA['patientUser'],
        'pasienB' => $patientB['pasien'],
        'patientUserB' => $patientB['patientUser'],
        'plainPatient' => $plainPatient,
        'pendamping' => $pendamping,
    ];
}

function validPatientPayload(array $overrides = []): array
{
    return array_merge([
        'nama' => 'Pasien Baru Super',
        'nik' => '3173999911110001',
        'tgl_lahir' => '1992-02-02',
        'jenis_kelamin' => 'Perempuan',
        'alamat' => 'Alamat baru',
        'no_hp' => '081234567890',
        'tipe_tb' => 'TB Paru',
        'jenjang_terapi' => 3,
        'tgl_diagnosa' => now()->toDateString(),
        'mulai_pengobatan' => now()->toDateString(),
        'selesai_pengobatan' => now()->copy()->addDays(84)->toDateString(),
    ], $overrides);
}

test('superadmin can list all patients', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['superadmin'])
        ->get(route('pasien.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/index')
            ->has('pasien.data', 2)
            ->where('canSelectPuskesmas', true)
        );
});

test('puskesmas admin only lists patients in their puskesmas', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['puskesmasAdminA'])
        ->get(route('pasien.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/index')
            ->has('pasien.data', 1)
            ->where('pasien.data.0.id', $world['pasienA']->id)
            ->where('canSelectPuskesmas', false)
        );
});

test('superadmin can open create patient form', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['superadmin'])
        ->get(route('pasien.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/create')
            ->where('canSelectPuskesmas', true)
            ->has('puskesmasOptions', 2)
        );
});

test('superadmin can create a patient for a selected puskesmas', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['superadmin'])
        ->post(route('pasien.store'), validPatientPayload([
            'puskesmas_id' => $world['puskesmasA']->id,
        ]))
        ->assertRedirect(route('pasien.index'));

    $created = Pasien::where('nama', 'Pasien Baru Super')->first();
    expect($created)->not->toBeNull()
        ->and((int) $created->puskesmas_id)->toBe((int) $world['puskesmasA']->id)
        ->and($created->user->can('Pasien'))->toBeTrue()
        ->and($created->user->password)->not->toBe('081234567890');
});

test('puskesmas admin can still create a patient scoped to their puskesmas', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['puskesmasAdminA'])
        ->post(route('pasien.store'), validPatientPayload([
            'nama' => 'Pasien PKM',
            'no_hp' => '081298765432',
        ]))
        ->assertRedirect(route('pasien.index'));

    $created = Pasien::where('nama', 'Pasien PKM')->first();
    expect($created)->not->toBeNull()
        ->and((int) $created->puskesmas_id)->toBe((int) $world['puskesmasA']->id);
});

test('superadmin can view patient detail', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['superadmin'])
        ->get(route('pasien.show', $world['pasienB']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pasien/show')
            ->where('pasien.id', $world['pasienB']->id)
            ->missing('pasien.user.password')
        );
});

test('superadmin can edit a patient', function () {
    $world = patientManagementWorld();
    $pasien = $world['pasienA'];

    $this->actingAs($world['superadmin'])
        ->put(route('pasien.update', $pasien), [
            'nama' => 'Pasien Alpha Diedit',
            'nik' => $pasien->nik,
            'tgl_lahir' => $pasien->tgl_lahir,
            'jenis_kelamin' => $pasien->jenis_kelamin,
            'alamat' => $pasien->alamat,
            'no_hp' => $world['patientUserA']->no_hp,
            'tipe_tb' => $pasien->tipe_tb,
            'jenjang_terapi' => $pasien->jenjang_terapi,
            'tgl_diagnosa' => $pasien->tgl_diagnosa,
            'mulai_pengobatan' => $pasien->mulai_pengobatan,
            'selesai_pengobatan' => $pasien->selesai_pengobatan,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('pasien.index'));

    expect($pasien->fresh()->nama)->toBe('Pasien Alpha Diedit');
});

test('superadmin can deactivate a patient without deleting medication history', function () {
    $world = patientManagementWorld();
    $pasien = $world['pasienA'];
    $patientUser = $world['patientUserA'];

    $obat = Obat::create(['nama_obat' => 'Rifampisin', 'deskripsi' => 'uji']);
    $obatPasien = ObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'active' => true,
    ]);
    $jadwal = JadwalObat::create([
        'obat_pasien_id' => $obatPasien->id,
        'obat_id' => $obat->id,
        'pasien_id' => $pasien->id,
        'mulai_tanggal' => now()->toDateString(),
        'selesai_tanggal' => now()->copy()->addDays(28)->toDateString(),
        'qty_diberikan' => 28,
        'satuan' => 'Tablet',
    ]);
    $history = HistoryJadwalObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'detail_jadwal_obat_id' => 1,
        'waktu' => now(),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
    ]);
    UserPushSubscription::create([
        'user_id' => $patientUser->id,
        'token' => 'token-pasien-a',
        'token_hash' => hash('sha256', 'token-pasien-a'),
        'platform' => 'android',
        'device_label' => 'hp-a',
    ]);
    $otherUser = User::factory()->create();
    UserPushSubscription::create([
        'user_id' => $otherUser->id,
        'token' => 'token-orang-lain',
        'token_hash' => hash('sha256', 'token-orang-lain'),
        'platform' => 'android',
        'device_label' => 'hp-lain',
    ]);

    $this->actingAs($world['superadmin'])
        ->from(route('pasien.index'))
        ->delete(route('pasien.delete', $pasien))
        ->assertRedirect(route('pasien.index'))
        ->assertSessionHas('success');

    expect(Pasien::find($pasien->id))->not->toBeNull()
        ->and($pasien->fresh()->status)->toBe('Non Aktif')
        ->and($patientUser->fresh()->active)->toBeFalse()
        ->and(HistoryJadwalObatPasien::find($history->id))->not->toBeNull()
        ->and(JadwalObat::find($jadwal->id))->not->toBeNull()
        ->and(ObatPasien::find($obatPasien->id))->not->toBeNull()
        ->and(UserPushSubscription::where('user_id', $patientUser->id)->count())->toBe(0)
        ->and(UserPushSubscription::where('user_id', $otherUser->id)->count())->toBe(1);
});

test('deactivated patient cannot log in', function () {
    $world = patientManagementWorld();
    $patientUser = $world['patientUserA'];
    $patientUser->update(['active' => false]);

    $this->post('/login', [
        'username' => $patientUser->username,
        'password' => 'password',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('patient cannot access admin patient management', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['plainPatient'])
        ->get(route('pasien.index'))
        ->assertForbidden();

    $this->actingAs($world['plainPatient'])
        ->delete(route('pasien.delete', $world['pasienA']))
        ->assertForbidden();
});

test('pendamping cannot delete a patient', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['pendamping'])
        ->delete(route('pasien.delete', $world['pasienA']))
        ->assertForbidden();
});

test('puskesmas admin cannot manage another puskesmas patient', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['puskesmasAdminA'])
        ->get(route('pasien.show', $world['pasienB']))
        ->assertForbidden();

    $this->actingAs($world['puskesmasAdminA'])
        ->delete(route('pasien.delete', $world['pasienB']))
        ->assertForbidden();
});

test('changing the delete url to an admin user id cannot delete that account', function () {
    $world = patientManagementWorld();
    $admin = $world['puskesmasAdminA'];

    $this->actingAs($world['superadmin'])
        ->delete(route('pasien.delete', 999999))
        ->assertNotFound();

    $this->actingAs($world['superadmin'])
        ->delete(route('pasien.delete', $admin->id));

    expect(User::find($admin->id))->not->toBeNull()
        ->and($admin->fresh()->active)->toBeTrue()
        ->and($admin->fresh()->can('Admin'))->toBeTrue();
});

test('patient delete refuses a pasien row attached to a privileged user', function () {
    $world = patientManagementWorld();
    $malformed = Pasien::create([
        'user_id' => $world['puskesmasAdminA']->id,
        'puskesmas_id' => $world['puskesmasA']->id,
        'nik' => '3173000000000001',
        'nama' => 'Bukan Pasien',
        'tgl_lahir' => '1980-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Alamat',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => now()->toDateString(),
        'mulai_pengobatan' => now()->toDateString(),
        'selesai_pengobatan' => now()->copy()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);

    $this->actingAs($world['superadmin'])
        ->delete(route('pasien.delete', $malformed))
        ->assertForbidden();

    expect($world['puskesmasAdminA']->fresh()->active)->toBeTrue()
        ->and($malformed->fresh()->status)->toBe('Aktif');
});

test('medication reminder command skips deactivated patients without crashing', function () {
    $world = patientManagementWorld();
    $pasien = $world['pasienA'];
    $patientUser = $world['patientUserA'];
    $obat = Obat::create(['nama_obat' => 'Obat Reminder', 'deskripsi' => 'uji']);
    HistoryJadwalObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'detail_jadwal_obat_id' => 1,
        'waktu' => now()->subMinutes(5),
        'dosis' => 1,
        'satuan' => 'Tablet',
        'sudah_minum' => false,
    ]);

    $this->actingAs($world['superadmin'])
        ->delete(route('pasien.delete', $pasien))
        ->assertRedirect();

    $push = Mockery::mock(FcmPushService::class);
    $push->shouldReceive('sendToUser')->never();
    $push->shouldReceive('tokensForUser')->never();
    app()->instance(FcmPushService::class, $push);

    $this->artisan('app:send-notif-minum-obat-cmd')->assertSuccessful();
    expect($patientUser->fresh()->active)->toBeFalse();
});

test('schedule generator skips inactive patients', function () {
    $world = patientManagementWorld();
    $pasien = $world['pasienA'];
    $obat = Obat::create(['nama_obat' => 'Obat Generator', 'deskripsi' => 'uji']);
    $obatPasien = ObatPasien::create([
        'pasien_id' => $pasien->id,
        'obat_id' => $obat->id,
        'active' => true,
    ]);
    $jadwal = JadwalObat::create([
        'obat_pasien_id' => $obatPasien->id,
        'obat_id' => $obat->id,
        'pasien_id' => $pasien->id,
        'mulai_tanggal' => now()->toDateString(),
        'selesai_tanggal' => now()->copy()->addDays(28)->toDateString(),
        'qty_diberikan' => 28,
        'satuan' => 'Tablet',
    ]);
    $jadwal->details()->create([
        'waktu' => '08:00:00',
        'dosis' => 1,
        'satuan' => 'Tablet',
    ]);

    $pasien->update(['status' => 'Non Aktif']);
    $world['patientUserA']->update(['active' => false]);

    $stats = app(MedicationScheduleGenerator::class)->generateForDate();

    expect($stats['history_created'])->toBe(0)
        ->and(HistoryJadwalObatPasien::where('pasien_id', $pasien->id)->count())->toBe(0);
});

test('creating a patient with a new username succeeds', function () {
    $world = patientManagementWorld();

    $this->actingAs($world['puskesmasAdminA'])
        ->post(route('pasien.store'), validPatientPayload([
            'nama' => 'Nazwa Naurah',
            'username' => 'nazwanew',
            'no_hp' => '081200000001',
        ]))
        ->assertRedirect(route('pasien.index'))
        ->assertSessionHasNoErrors();

    expect(User::where('username', 'nazwanew')->exists())->toBeTrue();
});

test('creating a patient with an existing username returns validation error not 500', function () {
    $world = patientManagementWorld();
    User::factory()->create(['username' => 'nazwan1']);

    $this->actingAs($world['puskesmasAdminA'])
        ->from(route('pasien.create'))
        ->post(route('pasien.store'), validPatientPayload([
            'nama' => 'Nazwa Naurah',
            'username' => 'nazwan1',
            'no_hp' => '081200000002',
        ]))
        ->assertRedirect(route('pasien.create'))
        ->assertSessionHasErrors([
            'username' => 'Username sudah digunakan, silakan pilih username lain.',
        ]);

    expect(Pasien::where('nama', 'Nazwa Naurah')->count())->toBe(0);
});

test('auto generated username skips an existing nazwan1 instead of 500', function () {
    $world = patientManagementWorld();
    User::factory()->create(['username' => 'nazwan1']);

    $this->actingAs($world['puskesmasAdminA'])
        ->post(route('pasien.store'), validPatientPayload([
            'nama' => 'Nazwa Naurah',
            'no_hp' => '081200000003',
        ]))
        ->assertRedirect(route('pasien.index'))
        ->assertSessionHasNoErrors();

    $created = Pasien::where('nama', 'Nazwa Naurah')->first();
    expect($created)->not->toBeNull()
        ->and($created->user->username)->toBe('nazwan2');
});

test('creating a patient may reuse an existing phone number', function () {
    $world = patientManagementWorld();
    $sharedPhone = $world['patientUserA']->no_hp;

    $this->actingAs($world['puskesmasAdminA'])
        ->post(route('pasien.store'), validPatientPayload([
            'nama' => 'Anak Satu Rumah',
            'username' => 'anaksatu',
            'no_hp' => $sharedPhone,
        ]))
        ->assertRedirect(route('pasien.index'))
        ->assertSessionHasNoErrors();

    expect(User::where('no_hp', $sharedPhone)->count())->toBeGreaterThan(1);
});
