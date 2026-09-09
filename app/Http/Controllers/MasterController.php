<?php

namespace App\Http\Controllers;

use App\Events\TestEvent;
use App\Models\Puskesmas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MasterController extends Controller
{
    public function typetb_index() {}
    public function edit_puskesmas()
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas) {
            return inertia('puskesmas/profile', compact('puskesmas'));
        } else {
            return redirect()->back()->with('errors', 'User tidak memiliki data puskesmas');
        }
    }
    public function update_puskesmas(Request $r)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas) {
            $puskesmas->update([
                'nama_puskesmas' => $r->nama_puskesmas,
                'kode_puskesmas' => $r->kode_puskesmas,
                'kecamatan' => $r->kecamatan,
                'kotakab' => $r->kotakab,
                'provinsi' => $r->provinsi,
                'alamat' => $r->alamat,
            ]);
            return redirect()->back()->with('success', 'Puskesmas berhasil dirubah');
        } else {
            return redirect()->back()->with('errors', 'User tidak memiliki data puskesmas');
        }
    }
}
