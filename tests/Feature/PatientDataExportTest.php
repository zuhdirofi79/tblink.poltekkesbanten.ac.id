<?php

use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

function exportPermissions(): void
{
    Permission::findOrCreate('Admin', 'web');
    Permission::findOrCreate('Superadmin', 'web');
    Permission::findOrCreate('Pasien', 'web');
    Permission::findOrCreate('Pendamping', 'web');
}

function exportWorld(): array
{
    exportPermissions();

    $superadmin = User::factory()->create(['username' => 'sa_export']);
    $superadmin->givePermissionTo('Superadmin');

    $adminA = User::factory()->create(['username' => 'admin_export_a']);
    $adminA->givePermissionTo('Admin');
    $puskesmasA = Puskesmas::create([
        'user_id' => $adminA->id,
        'nama_puskesmas' => 'PKM Export Alpha',
        'kode_puskesmas' => 'X'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);

    $adminB = User::factory()->create(['username' => 'admin_export_b']);
    $adminB->givePermissionTo('Admin');
    $puskesmasB = Puskesmas::create([
        'user_id' => $adminB->id,
        'nama_puskesmas' => 'PKM Export Beta',
        'kode_puskesmas' => 'Y'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);

    $patientUserA = User::factory()->create([
        'username' => 'pasien_export_a',
        'email' => 'alpha@example.test',
        'no_hp' => '08219256078',
        'password' => 'secret-password',
        'device_token' => 'fcm-token-should-not-export',
    ]);
    $patientUserA->givePermissionTo('Pasien');
    $pendamping = User::factory()->create(['name' => 'Pendamping Alpha']);
    $pendamping->givePermissionTo('Pendamping');

    $pasienA = Pasien::create([
        'user_id' => $patientUserA->id,
        'puskesmas_id' => $puskesmasA->id,
        'pendamping_id' => $pendamping->id,
        'nik' => '0801234567890123',
        'nama' => 'Pasien Alpha Export',
        'tgl_lahir' => '1990-01-02',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Jl. Melati No. 1',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => '2026-01-15',
        'mulai_pengobatan' => '2026-01-20',
        'selesai_pengobatan' => '2026-07-20',
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);

    $patientUserB = User::factory()->create(['username' => 'pasien_export_b']);
    $patientUserB->givePermissionTo('Pasien');
    $pasienB = Pasien::create([
        'user_id' => $patientUserB->id,
        'puskesmas_id' => $puskesmasB->id,
        'nik' => '3173999999990002',
        'nama' => 'Pasien Beta Export',
        'tgl_lahir' => '1992-02-02',
        'jenis_kelamin' => 'Perempuan',
        'alamat' => 'Alamat Beta',
        'tipe_tb' => 'TB Paru',
        'tgl_diagnosa' => now()->toDateString(),
        'mulai_pengobatan' => now()->toDateString(),
        'selesai_pengobatan' => now()->addDays(28)->toDateString(),
        'jenjang_terapi' => 3,
        'status' => 'Aktif',
    ]);

    $plainPatient = User::factory()->create(['username' => 'plain_export_pasien']);
    $plainPatient->givePermissionTo('Pasien');

    return compact(
        'superadmin',
        'adminA',
        'adminB',
        'puskesmasA',
        'puskesmasB',
        'pasienA',
        'pasienB',
        'patientUserA',
        'plainPatient',
        'pendamping',
    );
}

function spreadsheetFromExportResponse($response)
{
    $raw = $response->baseResponse;
    if ($raw instanceof StreamedResponse) {
        $content = $response->streamedContent();
    } elseif ($raw instanceof BinaryFileResponse) {
        $content = file_get_contents($raw->getFile()->getPathname());
    } else {
        $content = $response->getContent();
    }

    expect(substr((string) $content, 0, 2))->toBe('PK');

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($tmp, $content);
    $spreadsheet = IOFactory::load($tmp);
    @unlink($tmp);

    return $spreadsheet;
}

test('authorized superadmin can download excel', function () {
    $world = exportWorld();
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00', 'Asia/Jakarta'));

    $response = $this->actingAs($world['superadmin'])->get(route('report.data-pasien'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('data-pasien-2026-09-14.xlsx');

    $sheet = spreadsheetFromExportResponse($response)->getActiveSheet();
    expect($sheet->getTitle())->toBe('Data Pasien')
        ->and($sheet->getCell('A1')->getValue())->toBe('No')
        ->and($sheet->getCell('B1')->getValue())->toBe('Nama Pasien')
        ->and($sheet->getCell('C1')->getValue())->toBe('NIK');
});

test('authorized admin can download excel', function () {
    $world = exportWorld();

    $this->actingAs($world['adminA'])
        ->get(route('report.data-pasien'))
        ->assertOk();
});

test('unauthorized patient cannot download all patient data', function () {
    $world = exportWorld();

    $this->actingAs($world['plainPatient'])
        ->get(route('report.data-pasien'))
        ->assertForbidden();

    $this->actingAs($world['pendamping'])
        ->get(route('report.data-pasien'))
        ->assertForbidden();
});

test('admin only receives patients from own puskesmas', function () {
    $world = exportWorld();

    $sheet = spreadsheetFromExportResponse(
        $this->actingAs($world['adminA'])->get(route('report.data-pasien'))
    )->getActiveSheet();

    $names = [];
    foreach ($sheet->getRowIterator(2) as $row) {
        $names[] = (string) $sheet->getCell('B'.$row->getRowIndex())->getValue();
    }

    expect($names)->toContain('Pasien Alpha Export')
        ->and($names)->not->toContain('Pasien Beta Export');
});

test('excel contains patient records and keeps nik as text', function () {
    $world = exportWorld();

    $sheet = spreadsheetFromExportResponse(
        $this->actingAs($world['superadmin'])->get(route('report.data-pasien'))
    )->getActiveSheet();

    $alphaRow = null;
    foreach ($sheet->getRowIterator(2) as $row) {
        if ((string) $sheet->getCell('B'.$row->getRowIndex())->getValue() === 'Pasien Alpha Export') {
            $alphaRow = $row->getRowIndex();
            break;
        }
    }

    expect($alphaRow)->not->toBeNull();
    $nik = (string) $sheet->getCell('C'.$alphaRow)->getValue();
    $phone = (string) $sheet->getCell('E'.$alphaRow)->getValue();

    expect($nik)->toBe('0801234567890123')
        ->and($phone)->toBe('08219256078')
        ->and($sheet->getCell('D'.$alphaRow)->getValue())->toBe('alpha@example.test')
        ->and($sheet->getCell('N'.$alphaRow)->getValue())->toBe('PKM Export Alpha')
        ->and($sheet->getCell('O'.$alphaRow)->getValue())->toBe('Pendamping Alpha')
        ->and($sheet->getCell('P'.$alphaRow)->getValue())->toBe('Aktif');
});

test('empty patient result still produces valid xlsx with headers', function () {
    exportPermissions();
    $admin = User::factory()->create();
    $admin->givePermissionTo('Admin');
    Puskesmas::create([
        'user_id' => $admin->id,
        'nama_puskesmas' => 'PKM Kosong',
        'kode_puskesmas' => 'Z'.fake()->unique()->numerify('###'),
        'kecamatan' => 'Test',
        'kotakab' => 'Test',
        'provinsi' => 'Banten',
        'alamat' => 'Alamat',
    ]);

    $response = $this->actingAs($admin)->get(route('report.data-pasien'));
    $response->assertOk();

    $sheet = spreadsheetFromExportResponse($response)->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('No')
        ->and($sheet->getCell('P1')->getValue())->toBe('Status')
        ->and($sheet->getCell('B2')->getValue())->toBeNull();
});

test('export does not include password device token or fcm token', function () {
    $world = exportWorld();

    $sheet = spreadsheetFromExportResponse(
        $this->actingAs($world['superadmin'])->get(route('report.data-pasien'))
    )->getActiveSheet();

    $headers = [];
    foreach (range('A', 'P') as $col) {
        $headers[] = strtolower((string) $sheet->getCell($col.'1')->getValue());
    }
    $blob = strtolower($sheet->toArray()[1][3] ?? '').' '.strtolower(implode(' ', $headers));

    expect($headers)->not->toContain('password')
        ->and($headers)->not->toContain('device_token')
        ->and($headers)->not->toContain('fcm')
        ->and($blob)->not->toContain('secret-password')
        ->and($blob)->not->toContain('fcm-token-should-not-export');
});

test('patient with no medication history still exports without 500', function () {
    $world = exportWorld();

    $this->actingAs($world['superadmin'])
        ->get(route('report.data-pasien'))
        ->assertOk();
});
