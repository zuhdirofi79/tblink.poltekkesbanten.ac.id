<?php

namespace App\Http\Controllers;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalVisit;
use App\Models\LogPenggunaanObat;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function data_jadwal_minum_obat_pasien()
    {
        $pasien = auth()->user()?->relatedPasien();
        if (! $pasien) {
            return [
                'schedules' => [],
                'pasien' => null,
            ];
        }
        $jadwalHariIni = $pasien->historyJadwalObat()->whereBetween('waktu', [now()->startOfDay(), now()->endOfDay()])->with(['obat'])->orderBy('waktu')->get();
        return [
            'schedules' => $jadwalHariIni,
            'pasien' => $pasien,
        ];
    }
    public function sudah_minum_obat(HistoryJadwalObatPasien $sch)
    {
        $sch->update([
            'waktu_aktual_minum' => now(),
            'sudah_minum' => true,
        ]);
        $jadwalObat = $sch->detailJadwalObat?->jadwalObat;
        if ($jadwalObat) {
            $jadwalObat->logs()->create([
                'jadwal_minum_obat' => $sch->waktu,
                'aktual_minum_obat' => now(),
                'dosis_obat' => $sch->dosis,
                'satuan' => $sch->satuan,
                'user_id' => auth()->user()->id,
            ]);
        }
        $obatNama = $sch->obat?->nama_obat ?? 'obat';
        return redirect()->back()->with('success', 'Obat ' . $obatNama . ' ditandai sudah diminum');
    }
    public function skala_kepatuhan()
    {
        $psm = auth()->user()->puskesmas;
        $psns = Pasien::where('puskesmas_id', $psm->id)->pluck('id');
        $sudah = HistoryJadwalObatPasien::whereIn('pasien_id', $psns)->where('waktu', '<=', now())->where('sudah_minum', 1)->count();
        $belum = HistoryJadwalObatPasien::whereIn('pasien_id', $psns)->where('waktu', '<=', now())->where('sudah_minum', 0)->count();
        if ($sudah > 0 && $belum > 0) {
            $sudahPersen = $sudah / ($sudah + $belum) * 100;
            $belumPersen = $belum / ($sudah + $belum) * 100;
        } else {
            $sudahPersen = 0;
            $belumPersen = 0;
        }
        return [
            'sudah' => $sudah,
            'belum' => $belum,
            'sudahPersen' => $sudahPersen,
            'belumPersen' => $belumPersen,
        ];
    }
    public function admin_jadwal_berobat()
    {
        $schedules = JadwalVisit::whereDate('tgl_visit', today())->where('admin_id', auth()->user()->id)->with(['pasien', 'admin'])->get();
        return $schedules;
    }
    public function pasien_belum_minum_obat()
    {
        $psm = auth()->user()->puskesmas;
        $psns = Pasien::where('puskesmas_id', $psm->id)->pluck('id');
        $hists = HistoryJadwalObatPasien::whereBetween('waktu', [now()->subHour(2), now()->addHour(2)])->whereIn('pasien_id', $psns)->where('sudah_minum', 0)->with(['pasien.user', 'obat'])->get();
        Cache::put('belum_minum', $hists, now()->addHour(2));
        return $hists;
    }
    public function pasien_kurang_patuh()
    {
        $psm = auth()->user()->puskesmas;
        $psns = Pasien::where('puskesmas_id', $psm->id)->where('status', 'Aktif')->get();
        $pn = $psns->map(function ($m) {
            $dt = $m->kepatuhanMinumObat();
            $m['kepatuhan_percent'] = $dt['percent'];
            $m['kepatuhan_minum'] = $dt['minum'];
            $m['kepatuhan_tdk_minum'] = $dt['tdk_minum'];
            return $m;
        })->sortBy('kepatuhan_percent');
        return $pn->where('kepatuhan_percent', '<', 98)->take(5)->values();
    }
    public function ds_pendamping()
    {
        $pasien = Pasien::where('pendamping_id', auth()->user()->id)->first();
        return $pasien;
    }
    public function ds_sa_kepatuhan()
    {
        $sudah = HistoryJadwalObatPasien::where('waktu', '<=', now())->where('sudah_minum', 1)->count();
        $belum = HistoryJadwalObatPasien::where('waktu', '<=', now())->where('sudah_minum', 0)->count();
        if ($sudah > 0 && $belum > 0) {
            $sudahPersen = $sudah / ($sudah + $belum) * 100;
            $belumPersen = $belum / ($sudah + $belum) * 100;
        } else {
            $sudahPersen = 0;
            $belumPersen = 0;
        }
        return [
            'sudah' => $sudah,
            'belum' => $belum,
            'sudahPersen' => $sudahPersen,
            'belumPersen' => $belumPersen,
        ];
    }
    public function ds_sa_kurang_patuh()
    {
        $psns = Pasien::where('status', 'Aktif')->with('puskesmas')->get();
        $pn = $psns->map(function ($m) {
            $dt = $m->kepatuhanMinumObat();
            $m['kepatuhan_percent'] = $dt['percent'];
            $m['kepatuhan_minum'] = $dt['minum'];
            $m['kepatuhan_tdk_minum'] = $dt['tdk_minum'];
            return $m;
        })->sortBy('kepatuhan_percent');
        return $pn->where('kepatuhan_percent', '<', 98)->take(5)->values();
    }
}
