<?php

namespace App\Console\Commands;

use App\Jobs\SendFcmJob;
use App\Models\ObatPasien;
use App\Models\User;
use App\Notifications\StockObatPasien;
use App\Services\FcmPushService;
use Illuminate\Console\Command;

class CheckStockObatPasien extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-stock-obat-pasien';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(FcmPushService $push)
    {
        $ops = ObatPasien::where('active', 1)->get();
        foreach ($ops as $op) {
            $sisa_hari = $op->stock / $op->jadwal->details->sum('dosis');
            if ($sisa_hari < 3) {
                $usrs = User::whereHas('permissions', function ($p) {
                    $p->where('name', 'Admin');
                })->get();
                foreach ($usrs as $usr) {
                    $usr->notify(new StockObatPasien($op));
                    foreach ($push->tokensForUser($usr) as $token) {
                        SendFcmJob::dispatch('Stock Obat Pasien', 'Stock obat ' . $op->obat->nama . ' kurang dari 3 hari', $token, route('pasien.show', $op->pasien_id));
                    }
                }
            }
        }
    }
}
