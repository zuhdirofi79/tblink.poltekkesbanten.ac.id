<?php

use App\Console\Commands\GenerateJadwalMinumObat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('app:generate-jadwal-minum-obat')->everyFiveMinutes();
Schedule::command('app:send-notif-minum-obat-cmd')->everyMinute();
Schedule::command('app:send-jadwal-visit-reminders')->everyMinute();
Schedule::command('app:check-stock-obat-pasien')->dailyAt('01:00');
Schedule::call(function () {
    $line = '['.now()->toDateTimeString().' '.config('app.timezone').'] heartbeat schedule:run'.PHP_EOL;
    file_put_contents(storage_path('logs/scheduler.log'), $line, FILE_APPEND);
})->everyMinute()->name('scheduler-heartbeat');
