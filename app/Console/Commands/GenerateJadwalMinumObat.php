<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalObat;
use App\Models\ObatPasien;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateJadwalMinumObat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-jadwal-minum-obat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate jadwal minum obat harian';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('Generating...');
        $jos = JadwalObat::where('mulai_tanggal', '<=', date('Y-m-d'))->where('selesai_tanggal', '>=', date('Y-m-d'))->get();
        $bar = $this->output->createProgressBar(count($jos));
        foreach ($jos as $j) {
            $bar->advance();
            foreach ($j->details as $d) {
                $waktu = Carbon::create(date('Y-m-d') . ' ' . $d->waktu);
                $sch = HistoryJadwalObatPasien::where(['pasien_id' => $j->pasien_id, 'obat_id' => $j->obat_id, 'detail_jadwal_obat_id' => $d->id, 'waktu' => $waktu])->first();
                if ($sch) {
                    continue;
                } else {
                    $sch = HistoryJadwalObatPasien::create([
                        'pasien_id' => $j->pasien_id,
                        'obat_id' => $j->obat_id,
                        'detail_jadwal_obat_id' => $d->id,
                        'waktu' => $waktu,
                        'dosis' => $d->dosis,
                        'satuan' => $d->satuan
                    ]);
                }
            }
        }
        $bar->finish();
    }
}
