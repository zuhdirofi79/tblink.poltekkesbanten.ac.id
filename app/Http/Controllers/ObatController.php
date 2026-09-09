<?php

namespace App\Http\Controllers;

use App\Models\Obat;
use Illuminate\Http\Request;

class ObatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $r)
    {
        $obatq = Obat::query();
        if ($r->q) {
            $obatq->where(function ($f) use ($r) {
                $f->where('nama_obat', 'like', '%' . $r->q . '%')
                    ->orWhere('deskripsi', 'like', '%' . $r->q . '%');
            });
        }
        $obats = $obatq->orderBy('nama_obat')->paginate(10);
        return inertia('obat/index', compact('obats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('obat/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $r)
    {
        $r->validate([
            'nama_obat' => 'required',
            'deskripsi' => 'required',
        ]);
        $o = Obat::firstOrNew(['nama_obat' => $r->nama_obat]);
        $o->deskripsi = $r->deskripsi;
        $o->save();
        return redirect()->route('obat.index')->with('success', 'Obat ' . $o->nama_obat . ' berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(Obat $obat)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Obat $obat)
    {
        return inertia('obat/edit', compact('obat'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $r, Obat $obat)
    {
        $r->validate([
            'nama_obat' => 'required',
            'deskripsi' => 'required',
        ]);
        $obat->update([
            'nama_obat' => $r->nama_obat,
            'deskripsi' => $r->deskripsi
        ]);
        return redirect(route('obat.index'))->with('success', 'Obat ' . $obat->nama_obat . ' berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Obat $obat)
    {
        $nama = $obat->nama_obat;
        $obat->delete();
        return redirect()->back()->with('success', 'Obat ' . $nama . ' berhasil dihapus');
    }
}
