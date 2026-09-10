<?php

namespace App\Console\Commands;

use App\Services\MedicationScheduleGenerator;
use Illuminate\Console\Command;

class GenerateJadwalMinumObat extends Command
{
    protected $signature = 'app:generate-jadwal-minum-obat';

    protected $description = 'Generate jadwal minum obat harian dari JadwalObat yang berlaku hari ini';

    public function handle(MedicationScheduleGenerator $generator): int
    {
        $stats = $generator->generateForDate();

        $this->info('Date: '.$stats['date'].' ('.$stats['timezone'].')');
        $this->info('Eligible schedules: '.$stats['master_eligible'].' / '.$stats['master_total']);
        $this->info('Details: '.$stats['details_total']);
        $this->info('Existing: '.$stats['history_existing']);
        $this->info('Created: '.$stats['history_created']);
        $this->info('Skipped: '.$stats['skipped']);
        $this->info('Errors: '.$stats['errors']);

        return self::SUCCESS;
    }
}
