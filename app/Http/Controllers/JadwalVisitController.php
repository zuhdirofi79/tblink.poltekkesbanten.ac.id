<?php

namespace App\Http\Controllers;

use App\Models\JadwalVisit;
use App\Models\Pasien;
use Illuminate\Http\Request;

class JadwalVisitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $r)
    {
        $r->validate([
            'tgl' => 'date',
        ]);
        $tgl = $r->tgl ? $r->tgl : today()->format('Y-m-d');
        $jvs = JadwalVisit::with(['pasien', 'admin'])->whereDate('tgl_visit', $tgl)->paginate(10);
        $pasiens = Pasien::orderBy('nama')->get();
        return inertia('pasien/visit-pasien/index-admin', compact('jvs', 'tgl', 'pasiens'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Pasien $pasien, Request $r)
    {
        $jvs = JadwalVisit::where('pasien_id', $pasien->id)->orderBy('tgl_visit', 'desc')->paginate(10);
        return inertia('pasien/visit-pasien/create', compact('pasien', 'jvs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $r)
    {
        $r->validate([
            'tgl' => 'required|date',
            'jam' => 'required|date_format:H:i',
            'pasien_id' => 'required|exists:pasiens,id',
        ]);
        $pasien = Pasien::find($r->pasien_id);
        JadwalVisit::create([
            'pasien_id' => $pasien->id,
            'tgl_visit' => $r->tgl . ' ' . $r->jam,
            'keluhan' => $r->keluhan,
            'catatan' => $r->catatan,
            'admin_id' => $r->user()->id,
        ]);
        return redirect()->back()->with('success', 'Jadwal visit berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(JadwalVisit $jadwalVisit)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JadwalVisit $jadwalVisit)
    {
        return inertia('pasien/visit-pasien/edit', compact('jadwalVisit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $r, JadwalVisit $jadwalVisit)
    {
        $r->validate([
            'tgl' => 'required|date',
            'jam' => 'required|date_format:H:i',
            'pasien_id' => 'required|exists:pasiens,id',
        ]);
        $jadwalVisit->update([
            'tgl_visit' => $r->tgl . ' ' . $r->jam,
            'keluhan' => $r->keluhan,
            'catatan' => $r->catatan,
        ]);
        return redirect(route('visit.create', $jadwalVisit->pasien_id))->with('success', 'Jadwal visit berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JadwalVisit $jadwalVisit)
    {
        $jadwalVisit->delete();
        return redirect()->back()->with('success', 'Jadwal visit berhasil dihapus');
    }
    public function selesai_visit(JadwalVisit $jadwalVisit)
    {
        $jadwalVisit->update([
            'active' => 0,
        ]);
        return redirect()->back()->with('success', 'Jadwal berobat berhasil diselesaikan');
    }
}
