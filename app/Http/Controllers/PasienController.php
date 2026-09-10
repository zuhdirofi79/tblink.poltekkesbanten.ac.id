<?php

namespace App\Http\Controllers;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalVisit;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\TypeTb;
use App\Models\User;
use App\Services\MedicationScheduleGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PasienController extends Controller
{
    public function data_pasien()
    {
        $user = auth()->user();
        $pasien = $user->relatedPasien();
        if (! $pasien) {
            return redirect()->route('dashboard')->with('error', 'Data pasien tidak ditemukan untuk akun ini.');
        }
        $pasien->load('obats.obat', 'obats.jadwal.details', 'puskesmas', 'user', 'pendamping');
        return inertia('pasien/show', compact('pasien'));
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $r)
    {
        $puskesmas = auth()->user()->puskesmas;
        $pasienq = Pasien::with(['user'])->where('puskesmas_id', $puskesmas->id);
        if ($r->q) {
            $pasienq->where(function ($f) use ($r) {
                $f->where('nama', 'like', '%' . $r->q . '%')
                    ->orWhere('nik', 'like', '%' . $r->q . '%');
            });
        }
        $pasien = $pasienq->paginate(10);
        return inertia('pasien/index', compact('pasien'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tbs = TypeTb::orderBy('tipe_tb')->pluck('tipe_tb');
        return inertia('pasien/create', compact('tbs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $r)
    {
        $r->validate([
            'nama' => 'required',
            'nik' => 'required',
            'tgl_lahir' => 'required',
            'jenis_kelamin' => 'required',
            'alamat' => 'required',
            'no_hp' => 'required|unique:users,no_hp',
            'tipe_tb' => 'required',
            'jenjang_terapi' => 'required',
            'tgl_diagnosa' => 'nullable',
            'mulai_pengobatan' => 'required',
            'selesai_pengobatan' => 'nullable',
            'no_hp_pendamping' => 'required_if:nama_pendamping,!=,',
            'hub_dg_pasien' => 'required_if:nama_pendamping,!=,',
            'email_pendamping' => 'nullable',
        ]);
        if ($r->no_hp_pendamping == $r->no_hp) {
            return redirect()->back()->with('error', 'Nomor HP pendamping tidak boleh sama dengan nomor HP pasien');
        }
        $pw1 = $r->no_hp;
        $pw2 = $r->no_hp_pendamping;
        $user_pasien = User::create([
            'name' => $r->nama,
            'no_hp' => $r->no_hp,
            'username' => $this->getUsername($r->nama),
            'email' => $r->email,
            'password' => bcrypt($pw1),
        ]);
        $successMessage = 'Pasien berhasil ditambahkan, username: ' . $user_pasien->username . ' Password pasien: ' . $pw1;
        $user_pasien->givePermissionTo('Pasien');
        if ($r->nama_pendamping) {
            $user_pendamping = User::create([
                'name' => $r->nama_pendamping,
                'no_hp' => $r->no_hp_pendamping,
                'username' => $this->getUsername($r->nama_pendamping),
                'email' => $r->email_pendamping,
                'password' => bcrypt($pw2),
            ]);
            $user_pendamping->givePermissionTo('Pendamping');
            $successMessage .= ' Username pendamping: ' . $user_pendamping->username . '  Password pendamping: ' . $pw2;
        }
        $puskesmas = auth()->user()->puskesmas;
        $p = Pasien::create([
            'puskesmas_id' => $puskesmas->id,
            'nama' => $r->nama,
            'nik' => $r->nik,
            'tgl_lahir' => $r->tgl_lahir,
            'jenis_kelamin' => $r->jenis_kelamin,
            'alamat' => $r->alamat,
            'tipe_tb' => $r->tipe_tb,
            'jenjang_terapi' => $r->jenjang_terapi,
            'tgl_diagnosa' => $r->tgl_diagnosa,
            'mulai_pengobatan' => $r->mulai_pengobatan,
            'selesai_pengobatan' => $r->selesai_pengobatan,
            'user_id' => $user_pasien->id,
            'pendamping_id' => isset($user_pendamping) ? $user_pendamping->id : null,
            'hub_dg_pasien' => $r->hub_dg_pasien,
            'status' => 'Aktif',
        ]);
        return redirect(route('pasien.index'))->with('success', $successMessage);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        } else {
            $pasien->load(['user', 'pendamping', 'obats.obat', 'puskesmas', 'obats.jadwal.details']);
            return inertia('pasien/show', compact('pasien'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        } else {
            $userPasien = $pasien->user;
            $userPendamping = $pasien->pendamping;
            return inertia('pasien/edit', compact('pasien', 'userPasien', 'userPendamping'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $r, Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $r->validate([
            'nama' => 'required',
            'nik' => 'required',
            'tgl_lahir' => 'required',
            'jenis_kelamin' => 'required',
            'alamat' => 'required',
            'no_hp' => 'required|unique:users,no_hp,' . $pasien->user_id,
            'tipe_tb' => 'required',
            'jenjang_terapi' => 'required',
            'tgl_diagnosa' => 'nullable',
            'mulai_pengobatan' => 'required',
            'selesai_pengobatan' => 'nullable',
            'no_hp_pendamping' => 'required_if:nama_pendamping,!=,',
            'email_pendamping' => 'nullable',
            'status' => 'required',
            'hub_dg_pasien' => 'required_if:nama_pendamping,!=,',
        ]);
        $pasien->update([
            'nama' => $r->nama,
            'nik' => $r->nik,
            'tgl_lahir' => $r->tgl_lahir,
            'jenis_kelamin' => $r->jenis_kelamin,
            'alamat' => $r->alamat,
            'tipe_tb' => $r->tipe_tb,
            'jenjang_terapi' => $r->jenjang_terapi,
            'tgl_diagnosa' => $r->tgl_diagnosa,
            'mulai_pengobatan' => $r->mulai_pengobatan,
            'selesai_pengobatan' => $r->selesai_pengobatan,
            'status' => $r->status,
        ]);
        $pasien->user->update([
            'no_hp' => $r->no_hp,
            'email' => $r->email,
        ]);
        if ($r->nama_pendamping) {
            if ($pasien->pendamping) {
                $pasien->pendamping->update([
                    'no_hp' => $r->no_hp_pendamping,
                    'email' => $r->email_pendamping,
                ]);
                $pasien->hub_dg_pasien = $r->hub_dg_pasien;
            } else {
                $pend = $pasien->pendamping()->create([
                    'name' => $r->nama_pendamping,
                    'no_hp' => $r->no_hp_pendamping,
                    'username' => $this->getUsername($r->nama_pendamping),
                    'email' => $r->email_pendamping,
                    'password' => bcrypt($r->no_hp_pendamping),
                ]);
                $pasien->hub_dg_pasien = $r->hub_dg_pasien;
                $pasien->pendamping_id = $pend->id;
            }
            $pasien->save();
        }
        return redirect(route('pasien.index'))->with('success', 'Pasien ' . $pasien->nama . ' berhasil dirubah!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pasien $pasien)
    {
        $nama = $pasien->nama;
        return redirect()->back()->with('success', 'Pasien ' . $nama . ' berhasil dihapus!');
    }

    #region obat
    public function obat(Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $obatPasien = $pasien->obats()->with(['obat', 'jadwal'])->get();
        $today = now()->timezone((string) config('app.timezone'))->toDateString();
        $obatHariIni = HistoryJadwalObatPasien::where('pasien_id', $pasien->id)->with('obat')->whereDate('waktu', $today)->orderBy('waktu')->get();
        return inertia('pasien/obat/index', compact('pasien', 'obatPasien', 'obatHariIni'));
    }
    public function obatGenerate(Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $pasien->id);
        return redirect(route('pasien.obat.index', $pasien))->with('success', 'Jadwal obat berhasil di generate!');
    }
    public function deleteHistJadwalObat(HistoryJadwalObatPasien $historyJadwalObatPasien)
    {
        $pasien = $historyJadwalObatPasien->pasien;
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $historyJadwalObatPasien->delete();
        return redirect(route('pasien.obat.index', $pasien))->with('success', 'Jadwal minum obat berhasil dihapus!');
    }
    public function obatCreate(Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $obats = Obat::orderBy('nama_obat')->get();
        return inertia('pasien/obat/create', compact('pasien', 'obats'));
    }
    public function obatStore(Request $r, Pasien $pasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $r->validate([
            'obat_id' => 'required',
            'mulai_tanggal' => 'required|date',
            'selesai_tanggal' => 'required|date',
            'qty_diberikan' => 'required',
            'satuan' => 'required',
            'jadwal_obat' => 'required|array|min:1',
            'jadwal_obat.*.waktu' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'jadwal_obat.*.dosis' => 'required|numeric|min:0.1',
        ]);
        $op = ObatPasien::create([
            'obat_id' => $r->obat_id,
            'pasien_id' => $pasien->id,
            'active' => true,
        ]);
        $jo = $op->jadwal()->create([
            'obat_id' => $r->obat_id,
            'pasien_id' => $pasien->id,
            'mulai_tanggal' => $r->mulai_tanggal,
            'selesai_tanggal' => $r->selesai_tanggal,
            'qty_diberikan' => $r->qty_diberikan,
            'satuan' => $r->satuan,
        ]);
        foreach ($r->jadwal_obat as $j) {
            $jo->details()->create([
                'waktu' => $j['waktu'],
                'dosis' => $j['dosis'],
                'satuan' => $j['satuan'],
            ]);
        }
        app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $pasien->id);

        return redirect(route('pasien.obat.index', $pasien->id))->with('success', 'Obat berhasil ditambahkan!');
    }

    public function obatEdit(Pasien $pasien, ObatPasien $obatPasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $obats = Obat::orderBy('nama_obat')->get();
        $jadwalObat = $obatPasien->jadwal()->first();
        $detailJadwalObat = $jadwalObat->details()->get();
        return inertia('pasien/obat/edit', compact('pasien', 'obatPasien', 'obats', 'jadwalObat', 'detailJadwalObat'));
    }
    public function obatUpdate(Request $r, Pasien $pasien, ObatPasien $obatPasien)
    {
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $r->validate([
            'mulai_tanggal' => 'required|date',
            'selesai_tanggal' => 'required|date',
            'qty_diberikan' => 'required',
            'satuan' => 'required',
            'jadwal_obat' => 'required|array|min:1',
            'jadwal_obat.*.waktu' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'jadwal_obat.*.dosis' => 'required|numeric|min:0.1',
        ]);
        $obatPasien->jadwal()->update([
            'mulai_tanggal' => $r->mulai_tanggal,
            'selesai_tanggal' => $r->selesai_tanggal,
            'qty_diberikan' => $r->qty_diberikan,
            'satuan' => $r->satuan,
        ]);
        $obatPasien->jadwal()->first()->details()->delete();
        foreach ($r->jadwal_obat as $j) {
            $obatPasien->jadwal()->first()->details()->create([
                'waktu' => $j['waktu'],
                'dosis' => $j['dosis'],
                'satuan' => $j['satuan'],
            ]);
        }
        app(MedicationScheduleGenerator::class)->generateForDate(pasienId: $pasien->id);

        return redirect(route('pasien.obat.index', $pasien->id))->with('success', 'Obat berhasil diubah!');
    }
    #endregion
    #region notifikasi
    public function notifikasi_pasien()
    {
        $user = auth()->user();
        $notifs = $user->unreadNotifications()->orderBy('created_at', 'desc')->paginate(10);
        return inertia('notifikasi/pasien', compact('notifs'));
    }
    #endregion
    #region visit
    public function visit(Request $r)
    {
        $tgl = $r->tgl ? $r->tgl : Carbon::parse($r->tanggal)->format('Y-m-d');
        $jvs = JadwalVisit::with(['pasien', 'admin'])->where('tgl_visit', $r->tanggal)->paginate(10);
        return inertia('pasien/visit/index', compact('jvs'));
    }
    public function visit_pasien(Request $r)
    {
        $user = auth()->user();
        $pasien = null;
        if ($user->hasPermissionTo('Pasien') || $user->hasPermissionTo('Pendamping')) {
            $pasien = $user->relatedPasien();
        } else if ($user->hasPermissionTo('Admin')) {
            $r->validate([
                'pasien_id' => 'required',
            ]);
            $pasien = Pasien::find($r->pasien_id);
        }
        if (! $pasien) {
            return redirect()->route('dashboard')->with('error', 'Data pasien tidak ditemukan untuk akun ini.');
        }
        $jvs = JadwalVisit::with(['pasien', 'admin'])->where('pasien_id', $pasien->id)->orderBy('tgl_visit', 'desc')->paginate(10);
        return inertia('pasien/visit-pasien/index', compact('pasien', 'jvs'));
    }
    #endregion
    public function getUsername($name)
    {
        $rep = str_replace(' ', '', $name);
        $username = strtolower(substr($rep, 0, 6));
        $addRow = User::where('username', $username)->withTrashed()->count();
        $userRows  = User::whereRaw("username REGEXP '^{$username}(-[0-9]*)?$'")->withTrashed()->count();
        // $userRows = User::where('username', 'like', "{$username}%")->withTrashed()->count();
        $countUser = $userRows + $addRow + 1;

        return ($countUser > 0) ? "{$username}{$countUser}" : $username;
    }
    public function send_notif(Request $r, Pasien $pasien, \App\Services\FcmPushService $push)
    {
        $r->validate([
            'judul' => 'required',
            'isi' => 'required',
        ]);
        Cache::put('tst', $r->all());
        $puskesmas = auth()->user()->puskesmas;
        if ($puskesmas->id != $pasien->puskesmas_id) {
            abort(403);
        }
        $result = $push->sendToUser($pasien->user, $r->judul, $r->isi);
        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'reason' => $result['reason'],
                'delivery' => $result['delivery'],
            ], 422);
        }

        return response()->json([
            'status' => 'FCM_ACCEPTED',
            'delivery' => $result['delivery'],
            'subscriptions_sent' => $result['subscriptions_sent'] ?? 0,
        ]);
    }
}
