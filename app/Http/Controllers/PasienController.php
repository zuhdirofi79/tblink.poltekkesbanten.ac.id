<?php

namespace App\Http\Controllers;

use App\Models\HistoryJadwalObatPasien;
use App\Models\JadwalVisit;
use App\Models\Obat;
use App\Models\ObatPasien;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\TypeTb;
use App\Models\User;
use App\Services\FcmPushService;
use App\Services\MedicationScheduleGenerator;
use App\Services\ManualMedicationReminderService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        $this->authorize('viewAny', Pasien::class);
        $pasienq = $this->scopedPasienQuery()->with(['user', 'puskesmas', 'pendamping']);
        if ($r->q) {
            $pasienq->where(function ($f) use ($r) {
                $f->where('nama', 'like', '%' . $r->q . '%')
                    ->orWhere('nik', 'like', '%' . $r->q . '%');
            });
        }
        $pasien = $pasienq->paginate(10);
        $canSelectPuskesmas = $this->actor()->canManageAllPatients();
        return inertia('pasien/index', compact('pasien', 'canSelectPuskesmas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Pasien::class);
        $tbs = TypeTb::orderBy('tipe_tb')->pluck('tipe_tb');
        $canSelectPuskesmas = $this->actor()->canManageAllPatients();
        $puskesmasOptions = $canSelectPuskesmas
            ? Puskesmas::orderBy('nama_puskesmas')->get(['id', 'nama_puskesmas'])
            : collect();
        return inertia('pasien/create', compact('tbs', 'canSelectPuskesmas', 'puskesmasOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $r)
    {
        $this->authorize('create', Pasien::class);
        $r->validate([
            'nama' => 'required',
            'nik' => 'required',
            'tgl_lahir' => 'required',
            'jenis_kelamin' => 'required',
            'alamat' => 'required',
            'no_hp' => 'required',
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'tipe_tb' => 'required',
            'jenjang_terapi' => 'required',
            'tgl_diagnosa' => 'nullable',
            'mulai_pengobatan' => 'required',
            'selesai_pengobatan' => 'nullable',
            'no_hp_pendamping' => 'required_if:nama_pendamping,!=,',
            'hub_dg_pasien' => 'required_if:nama_pendamping,!=,',
            'email_pendamping' => 'nullable',
        ], [
            'username.unique' => 'Username sudah digunakan, silakan pilih username lain.',
        ]);
        if ($r->no_hp_pendamping == $r->no_hp) {
            return redirect()->back()->with('error', 'Nomor HP pendamping tidak boleh sama dengan nomor HP pasien');
        }
        $puskesmasId = $this->resolvePuskesmasId($r);

        try {
            return DB::transaction(function () use ($r, $puskesmasId) {
                $pw1 = $r->no_hp;
                $pw2 = $r->no_hp_pendamping;
                $username = filled($r->username)
                    ? trim((string) $r->username)
                    : $this->getUsername($r->nama);

                $user_pasien = User::create([
                    'name' => $r->nama,
                    'no_hp' => $r->no_hp,
                    'username' => $username,
                    'email' => $r->email,
                    'password' => bcrypt($pw1),
                ]);
                $successMessage = 'Pasien berhasil ditambahkan, username: ' . $user_pasien->username . ' Password pasien: ' . $pw1;
                $user_pasien->givePermissionTo('Pasien');
                $user_pendamping = null;
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
                Pasien::create([
                    'puskesmas_id' => $puskesmasId,
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
                    'pendamping_id' => $user_pendamping?->id,
                    'hub_dg_pasien' => $r->hub_dg_pasien,
                    'status' => 'Aktif',
                ]);

                return redirect(route('pasien.index'))->with('success', $successMessage);
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains(strtolower($e->getMessage()), 'username')) {
                throw ValidationException::withMessages([
                    'username' => 'Username sudah digunakan, silakan pilih username lain.',
                ]);
            }
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pasien $pasien)
    {
        $this->authorize('view', $pasien);
        $pasien->load(['user', 'pendamping', 'obats.obat', 'puskesmas', 'obats.jadwal.details']);
        return inertia('pasien/show', compact('pasien'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pasien $pasien)
    {
        $this->authorize('update', $pasien);
        $userPasien = $pasien->user;
        $userPendamping = $pasien->pendamping;
        $canSelectPuskesmas = $this->actor()->canManageAllPatients();
        $puskesmasOptions = $canSelectPuskesmas
            ? Puskesmas::orderBy('nama_puskesmas')->get(['id', 'nama_puskesmas'])
            : collect();
        $tbs = TypeTb::orderBy('tipe_tb')->pluck('tipe_tb');
        return inertia('pasien/edit', compact('pasien', 'userPasien', 'userPendamping', 'canSelectPuskesmas', 'puskesmasOptions', 'tbs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $r, Pasien $pasien)
    {
        $this->authorize('update', $pasien);
        $r->validate([
            'nama' => 'required',
            'nik' => 'required',
            'tgl_lahir' => 'required',
            'jenis_kelamin' => 'required',
            'alamat' => 'required',
            'no_hp' => 'required',
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
        if ($this->actor()->canManageAllPatients() && $r->filled('puskesmas_id')) {
            $r->validate([
                'puskesmas_id' => 'exists:puskesmas,id',
            ]);
            $pasien->update(['puskesmas_id' => $r->puskesmas_id]);
        }
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
    public function destroy(Pasien $pasien, FcmPushService $push)
    {
        $this->authorize('delete', $pasien);

        $patientUser = $pasien->user;
        if (! $patientUser || ! $patientUser->can('Pasien')) {
            abort(403);
        }
        if ($patientUser->can('Admin') || $patientUser->can('Superadmin') || $patientUser->can('User Management')) {
            abort(403);
        }

        $nama = $pasien->nama;
        DB::transaction(function () use ($pasien, $patientUser, $push) {
            $pasien->update(['status' => 'Non Aktif']);
            $patientUser->update(['active' => false]);
            $push->forgetCurrentDevice($patientUser, null);
        });

        return redirect()->back()->with('success', 'Akun pasien '.$nama.' berhasil dinonaktifkan.');
    }

    #region obat
    public function obat(Pasien $pasien)
    {
        $this->authorize('view', $pasien);
        $obatPasien = $pasien->obats()->with(['obat', 'jadwal.details'])->get();
        $today = now()->timezone((string) config('app.timezone'))->toDateString();
        $obatHariIni = HistoryJadwalObatPasien::query()
            ->visibleOnBoard()
            ->where('pasien_id', $pasien->id)
            ->with('obat')
            ->whereDate('waktu', $today)
            ->orderBy('waktu')
            ->get();
        return inertia('pasien/obat/index', compact('pasien', 'obatPasien', 'obatHariIni'));
    }
    public function obatGenerate(Pasien $pasien)
    {
        $this->authorize('view', $pasien);
        $stats = app(MedicationScheduleGenerator::class)->generateForDate(
            pasienId: $pasien->id,
            restoreManualDeletions: true,
        );

        $created = (int) $stats['history_created'];
        $existing = (int) $stats['history_existing'];
        $skipped = (int) $stats['skipped'];
        $errors = (int) $stats['errors'];
        $skipReasons = $stats['skip_reasons'] ?? [];

        $summary = $created.' jadwal dibuat, '.$existing.' sudah ada, '.$skipped.' dilewati, '.$errors.' error.';
        $skipNote = $this->formatGenerateSkipNote($skipReasons);
        if ($skipNote !== '') {
            $summary .= ' '.$skipNote;
        }

        if ($created > 0) {
            return redirect(route('pasien.obat.index', $pasien))
                ->with('success', 'Jadwal obat berhasil digenerate. '.$summary);
        }

        if ($existing > 0) {
            return redirect(route('pasien.obat.index', $pasien))
                ->with('success', 'Tidak ada jadwal baru. '.$summary);
        }

        return redirect(route('pasien.obat.index', $pasien))
            ->with('error', 'Tidak ada jadwal obat aktif untuk hari ini.'.($skipNote !== '' ? ' '.$skipNote : ''));
    }

    /**
     * @param  array<string, int>  $skipReasons
     */
    private function formatGenerateSkipNote(array $skipReasons): string
    {
        if ($skipReasons === []) {
            return '';
        }

        $labels = [
            'inactive' => 'Non Aktif',
            'no_jadwal' => 'belum memiliki jadwal pengobatan',
            'no_details' => 'belum memiliki jadwal waktu minum',
            'outside_period' => 'di luar periode pengobatan',
            'invalid_time' => 'waktu tidak valid',
            'inactive_patient' => 'pasien tidak aktif',
            'manually_deleted' => 'dihapus manual',
            'exception' => 'error',
        ];

        $parts = [];
        foreach ($skipReasons as $reason => $count) {
            $label = $labels[$reason] ?? $reason;
            $parts[] = $count.' '.$label;
        }

        return 'Alasan dilewati: '.implode(', ', $parts).'.';
    }

    public function storeManualReminder(Request $r, Pasien $pasien, ManualMedicationReminderService $manualReminder)
    {
        $this->authorize('view', $pasien);
        $r->validate([
            'obat_pasien_id' => 'required|integer',
            'waktu' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        $history = $manualReminder->create(
            $pasien,
            (int) $r->obat_pasien_id,
            (string) $r->waktu,
            (int) auth()->id(),
        );

        $jam = optional($history->waktu)->timezone((string) config('app.timezone'))->format('H:i');

        return redirect(route('pasien.obat.index', $pasien))->with(
            'success',
            'Jadwal uji notifikasi berhasil dibuat untuk pukul '.$jam.'.'
        );
    }

    public function cancelManualReminder(Pasien $pasien, HistoryJadwalObatPasien $history, ManualMedicationReminderService $manualReminder)
    {
        $this->authorize('update', $pasien);
        if ((int) $history->pasien_id !== (int) $pasien->id) {
            abort(403);
        }

        $manualReminder->cancel($history);

        return redirect(route('pasien.obat.index', $pasien))->with('success', 'Jadwal uji notifikasi dibatalkan.');
    }
    public function deleteHistJadwalObat(Pasien $pasien, $history)
    {
        return $this->destroyMedicationHistoryInstance($pasien, (int) $history);
    }

    public function deleteHistJadwalObatCompat($history)
    {
        $row = HistoryJadwalObatPasien::query()->find((int) $history);
        if (! $row || ! $row->pasien) {
            Log::warning('[MEDICATION_DELETE_NOT_FOUND]', [
                'patient_id' => null,
                'history_id' => (int) $history,
                'user_id' => auth()->id(),
            ]);
            abort(404);
        }

        return $this->destroyMedicationHistoryInstance($row->pasien, (int) $history);
    }

    private function destroyMedicationHistoryInstance(Pasien $pasien, int $historyId)
    {
        $userId = auth()->id();

        Log::info('[MEDICATION_DELETE_START]', [
            'patient_id' => $pasien->id,
            'history_id' => $historyId,
            'user_id' => $userId,
        ]);

        $history = HistoryJadwalObatPasien::query()->find($historyId);
        if (! $history) {
            Log::warning('[MEDICATION_DELETE_NOT_FOUND]', [
                'patient_id' => $pasien->id,
                'history_id' => $historyId,
                'user_id' => $userId,
            ]);
            abort(404);
        }

        if ((int) $history->pasien_id !== (int) $pasien->id) {
            Log::warning('[MEDICATION_DELETE_FORBIDDEN]', [
                'patient_id' => $pasien->id,
                'history_id' => $historyId,
                'user_id' => $userId,
            ]);
            abort(403);
        }

        if (auth()->user()?->cannot('update', $pasien)) {
            Log::warning('[MEDICATION_DELETE_FORBIDDEN]', [
                'patient_id' => $pasien->id,
                'history_id' => $historyId,
                'user_id' => $userId,
            ]);
            abort(403);
        }

        try {
            $wasManualTest = $history->isManualTest();
            $tombstone = [
                'pasien_id' => $history->pasien_id,
                'obat_id' => $history->obat_id,
                'detail_jadwal_obat_id' => $history->detail_jadwal_obat_id,
                'waktu' => $history->waktu,
                'dosis' => $history->dosis,
                'satuan' => $history->satuan,
                'sudah_minum' => false,
                'source' => HistoryJadwalObatPasien::SOURCE_MANUALLY_DELETED,
            ];

            $history->delete();

            if (! $wasManualTest) {
                HistoryJadwalObatPasien::create($tombstone);
            }
        } catch (Throwable $e) {
            Log::error('[MEDICATION_DELETE_EXCEPTION]', [
                'patient_id' => $pasien->id,
                'history_id' => $historyId,
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('[MEDICATION_DELETE_SUCCESS]', [
            'patient_id' => $pasien->id,
            'history_id' => $historyId,
            'user_id' => $userId,
        ]);

        return redirect(route('pasien.obat.index', $pasien))->with('success', 'Jadwal minum obat berhasil dihapus!');
    }
    public function obatCreate(Pasien $pasien)
    {
        $this->authorize('view', $pasien);
        $obats = Obat::orderBy('nama_obat')->get();
        return inertia('pasien/obat/create', compact('pasien', 'obats'));
    }
    public function obatStore(Request $r, Pasien $pasien)
    {
        $this->authorize('view', $pasien);
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
        $this->authorize('view', $pasien);
        $obats = Obat::orderBy('nama_obat')->get();
        $jadwalObat = $obatPasien->jadwal()->first();
        $detailJadwalObat = $jadwalObat->details()->get();
        return inertia('pasien/obat/edit', compact('pasien', 'obatPasien', 'obats', 'jadwalObat', 'detailJadwalObat'));
    }
    public function obatUpdate(Request $r, Pasien $pasien, ObatPasien $obatPasien)
    {
        $this->authorize('view', $pasien);
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
        $user = $r->user();
        if (! $user) {
            return redirect()->route('login');
        }
        $pasien = null;
        if ($user->can('Pasien') || $user->can('Pendamping')) {
            $pasien = $user->relatedPasien();
        } elseif ($user->canManagePatients()) {
            $r->validate([
                'pasien_id' => 'required',
            ]);
            $pasien = Pasien::find($r->pasien_id);
            if ($pasien) {
                $this->authorize('view', $pasien);
            }
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
        $rep = str_replace(' ', '', (string) $name);
        $base = strtolower(substr($rep, 0, 6));
        if ($base === '') {
            $base = 'pasien';
        }

        $i = 1;
        do {
            $candidate = $base.$i;
            $i++;
        } while (User::withTrashed()->where('username', $candidate)->exists());

        return $candidate;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user, 403);

        return $user;
    }

    private function scopedPasienQuery()
    {
        $query = Pasien::query();
        if ($this->actor()->canManageAllPatients()) {
            return $query;
        }

        $puskesmasId = $this->actor()->puskesmas?->id;
        abort_unless($puskesmasId, 403);

        return $query->where('puskesmas_id', $puskesmasId);
    }

    private function resolvePuskesmasId(Request $r): int
    {
        if ($this->actor()->canManageAllPatients()) {
            $r->validate([
                'puskesmas_id' => 'required|exists:puskesmas,id',
            ]);

            return (int) $r->puskesmas_id;
        }

        $puskesmasId = $this->actor()->puskesmas?->id;
        abort_unless($puskesmasId, 403);

        return (int) $puskesmasId;
    }
    public function send_notif(Request $r, Pasien $pasien, \App\Services\FcmPushService $push)
    {
        $r->validate([
            'judul' => 'required',
            'isi' => 'required',
        ]);
        Cache::put('tst', $r->all());
        $this->authorize('view', $pasien);
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
