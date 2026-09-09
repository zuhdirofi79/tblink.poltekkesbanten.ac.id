<?php

namespace App\Http\Controllers;

use App\Models\TypeTb;
use Illuminate\Http\Request;

class TypeTbController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tbs = TypeTb::orderBy('tipe_tb', 'asc')->get();
        return inertia('typetb/index', compact('tbs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('typetb/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipe_tb' => 'required|unique:type_tbs,tipe_tb',
        ]);

        TypeTb::create(['tipe_tb' => $request->tipe_tb]);
        return redirect()->route('typetb.index')->with('success', 'Data berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(TypeTb $typeTb)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TypeTb $typeTb)
    {
        return inertia('typetb/edit', compact('typeTb'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TypeTb $typeTb)
    {
        $request->validate([
            'tipe_tb' => 'required|unique:type_tbs,tipe_tb,' . $typeTb->id,
        ]);

        $typeTb->update(['tipe_tb' => $request->tipe_tb]);
        return redirect()->route('typetb.index')->with('success', 'Data berhasil diubah');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TypeTb $typeTb)
    {
        $typeTb->delete();
        return redirect()->route('typetb.index')->with('success', 'Data berhasil dihapus');
    }
}
