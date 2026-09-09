<?php

use App\Http\Controllers\BerobatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JadwalVisitController;
use App\Http\Controllers\LiveChatController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ObatController;
use App\Http\Controllers\PasienController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TypeTbController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('dashboard');
})->name('home');

Route::get('/firebase-sw-config.js', \App\Http\Controllers\FirebaseSwConfigController::class)
    ->name('firebase.sw-config');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});
Route::prefix('jadwal-visit')->controller(JadwalVisitController::class)->middleware(['auth', 'can:Admin'])->name('visit.')->group(function () {
    Route::get('/create/{pasien}', 'create')->name('create');
    Route::post('/store', 'store')->name('store');
    Route::get('/edit/{jadwalVisit}', 'edit')->name('edit');
    Route::put('/update/{jadwalVisit}', 'update')->name('update');
    Route::delete('/delete/{jadwalVisit}', 'destroy')->name('delete');
    Route::post('/selesai-visit/{jadwalVisit}', 'selesai_visit')->name('selesai-visit');
    Route::get('/', 'index')->name('index');
});
Route::get('notifikasi-admin', [UserController::class, 'notifikasi_admin'])->middleware(['auth', 'can:Admin'])->name('notifikasi-admin');
Route::prefix('pasien')->name('pasien.')->group(function () {
    Route::prefix('visit-pasien')->controller(PasienController::class)->name('visit-pasien.')->group(function () {
        Route::get('/', 'visit_pasien')->name('index');
    });
    Route::get('/data-pasien', [PasienController::class, 'data_pasien'])->name('data-pasien');
    Route::get('notifikasi', [PasienController::class, 'notifikasi_pasien'])->middleware('auth')->name('notifikasi');
    Route::middleware(['auth', 'can:Admin'])->controller(PasienController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/show/{pasien}', 'show')->name('show');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/edit/{pasien}', 'edit')->name('edit');
        Route::put('/update/{pasien}', 'update')->name('update');
        Route::delete('/delete/{pasien}', 'destroy')->name('delete');
        Route::get('/obat/{pasien}/index', 'obat')->name('obat.index');
        Route::post('/obat/{pasien}/generate', 'obatGenerate')->name('obat.generate');
        Route::delete('/obat/hist/{jo}', 'deleteHistJadwalObat')->name('obat.hist.delete');
        Route::get('/obat/{pasien}/create', 'obatCreate')->name('obat.create');
        Route::post('/obat/{pasien}/store', 'obatStore')->name('obat.store');
        Route::get('/obat/{pasien}/edit/{obatPasien}', 'obatEdit')->name('obat.edit');
        Route::put('/obat/{pasien}/update/{obatPasien}', 'obatUpdate')->name('obat.update');
        Route::get('/visit', 'visit')->name('visit.index');
        Route::post('/send-notif/{pasien}', 'send_notif')->name('send-notif');
    });
});
Route::prefix('ds-data')->name('ds-data.')->controller(DashboardController::class)->middleware(['auth'])->group(function () {
    Route::prefix('pasien')->name('pasien.')->group(function () {
        Route::get('/jadwal-minum-obat', 'data_jadwal_minum_obat_pasien')->name('jadwal-minum-obat');
        Route::post('/sudah-minum-obat/{sch}', 'sudah_minum_obat')->name('sudah-minum-obat');
    });
    Route::prefix('pendamping')->name('pendamping.')->group(function () {
        Route::get('/', 'ds_pendamping')->name('index');
    });
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/jadwal-berobat', 'admin_jadwal_berobat')->name('jadwal-berobat');
        Route::get('/skala-kepatuhan', 'skala_kepatuhan')->name('skala-kepatuhan');
        Route::get('/pasien-belum-minum-obat', 'pasien_belum_minum_obat')->name('pasien-belum-minum-obat');
        Route::get('/pasien-kurang-patuh', 'pasien_kurang_patuh')->name('pasien-kurang-patuh');
    });
    Route::prefix('sa')->name('sa.')->group(function () {
        Route::get('/skala-kepatuhan', 'ds_sa_kepatuhan')->name('skala-kepatuhan');
        Route::get('/pasien-kurang-patuh', 'ds_sa_kurang_patuh')->name('pasien-kurang-patuh');
    });
});
Route::prefix('obat')->name('obat.')->group(function () {
    Route::middleware(['auth', 'can:Admin'])->controller(ObatController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/edit/{obat}', 'edit')->name('edit');
        Route::put('/update/{obat}', 'update')->name('update');
        Route::delete('/delete/{obat}', 'destroy')->name('delete');
    });
});
Route::middleware('auth')->post('/test', [MasterController::class, 'test'])->name('test-post');
Route::post('/user/register-device', [UserController::class, 'register_device'])->name('user.register-device')->middleware('auth');
Route::post('/user/unregister-device', [UserController::class, 'unregister_device'])->name('user.unregister-device')->middleware('auth');
Route::post('/user/test-notif', [UserController::class, 'send_test_notif'])->name('user.test-notif')->middleware('auth');
Route::controller(UserController::class)->middleware(['auth', 'can:User Management'])->prefix('user')->name('users.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/edit/{user}', 'edit')->name('edit');
    Route::put('/update/{user}', 'update')->name('update');
    Route::delete('/delete/{user}', 'destroy')->name('delete');
});
Route::controller(UserController::class)->middleware('auth')->prefix('notif')->name('notif.')->group(function () {
    Route::post('/mark-read/{notification}', 'read_notification')->name('mark-read');
});
Route::controller(MasterController::class)->middleware(['auth', 'can:Admin'])->prefix('master')->name('master.')->group(function () {
    Route::get('/puskesmas', 'edit_puskesmas')->name('puskesmas');
    Route::put('/puskesmas/update', 'update_puskesmas')->name('puskesmas.update');
});
Route::controller(LiveChatController::class)->middleware('auth')->prefix('live-chat')->name('live-chat.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/admin', 'index_admin')->name('admin');
    Route::get('/superadmin', 'index_sa')->name('sa');
    Route::get('/room/{receiver}', 'chat_room')->name('room');
    Route::post('/mark-read', 'mark_read')->name('mark-read');
});
Route::controller(ReportController::class)->middleware(['auth'])->prefix('report')->name('report.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/data-pasien', 'data_pasien')->name('data-pasien');
});
Route::controller(TypeTbController::class)->middleware(['auth', 'can:Superadmin'])->prefix('typetb')->name('typetb.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/edit/{type_tb}', 'edit')->name('edit');
    Route::put('/update/{type_tb}', 'update')->name('update');
    Route::delete('/delete/{type_tb}', 'destroy')->name('delete');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
