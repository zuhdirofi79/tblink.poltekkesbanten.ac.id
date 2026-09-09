<?php

use App\Console\Commands\GenerateJadwalMinumObat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('app:generate-jadwal-minum-obat')->hourly();
Schedule::command('app:send-notif-minum-obat-cmd')->everyMinute();
Schedule::command('app:check-stock-obat-pasien')->dailyAt('01:00');
