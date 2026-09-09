<?php

namespace App\Console\Commands;

use App\Models\HistoryJadwalObatPasien;
use App\Models\LogPenggunaanObat;
use App\Models\ObatPasien;
use Google\Service\AirQuality\Resource\History;
use Illuminate\Console\Command;
use ParagonIE\Sodium\Core\Curve25519\H;

class RandomTidakMinumCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:random-tidak-minum-cmd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $op = ObatPasien::all();
        $this->line(HistoryJadwalObatPasien::count());
        $cnt = 0;
        foreach ($op as $o) {
            $rand = rand(0, 4);
            if ($rand > 0) {
                for ($i = 0; $i < $rand; $i++) {
                    $det = $o->jadwal->details()->inRandomOrder()->first();
                    $h = HistoryJadwalObatPasien::where([
                        'detail_jadwal_obat_id' => $det->id,
                    ])->inRandomOrder()->first();
                    if ($h) {
                        $l = LogPenggunaanObat::where('jadwal_minum_obat', $h->waktu)->first();
                        $h->update([
                            'sudah_minum' => false,
                            'waktu_aktual_minum' => null
                        ]);
                        $l->delete();
                        $cnt++;
                    }
                }
            }
        }
        $this->line($cnt);
    }
}
