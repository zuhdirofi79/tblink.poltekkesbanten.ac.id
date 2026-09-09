<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Models\LogPenggunaanObat;
use App\Models\ObatPasien;
use Illuminate\Console\Command;

class KoreksiTestDataCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:koreksi-test-data-cmd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Koreksi test data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ops = ObatPasien::all();
        $this->line('Processing...');
        $bar = $this->output->createProgressBar(count($ops));
        foreach ($ops as $op) {
            $bar->advance();
            if ($op->stock < 1) {
                $jdid = $op->jadwal()->pluck('id');
                for ($i = 0; $i < abs($op->stock) + 1; $i++) {
                    $lg = LogPenggunaanObat::whereIn('jadwal_obat_id', $jdid)->orderBy('jadwal_minum_obat', 'desc')->first();
                    $hst = HistoryJadwalObatPasien::where([
                        'pasien_id' => $op->pasien_id,
                        'obat_id' => $op->obat_id,
                    ])->orderBy('waktu', 'desc')->first();
                    $hst->delete();
                    $lg->delete();
                }
            }
        }
        $bar->finish();
    }
}
