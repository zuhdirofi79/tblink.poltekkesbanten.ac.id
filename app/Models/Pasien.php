<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pasien extends Model
{
    /** @use HasFactory<\Database\Factories\PasienFactory> */
    use HasFactory;
    protected $fillable = [
        'user_id',
        'puskesmas_id',
        'nik',
        'nama',
        'tgl_lahir',
        'jenis_kelamin',
        'alamat',
        'tipe_tb',
        'tgl_diagnosa',
        'mulai_pengobatan',
        'selesai_pengobatan',
        'pendamping_id',
        'hub_dg_pasien',
        'jenjang_terapi',
        'status',
    ];
    protected $attributes = [
        'hub_dg_pasien' => 'Keluarga',
    ];
    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class, 'puskesmas_id');
    }

    public function pendamping()
    {
        return $this->belongsTo(User::class, 'pendamping_id');
    }
    public function historyJadwalObat()
    {
        return $this->hasMany(HistoryJadwalObatPasien::class, 'pasien_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    /**
     * Get all of the obats for the Pasien
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function obats(): HasMany
    {
        return $this->hasMany(ObatPasien::class);
    }
    public function kepatuhanMinumObat()
    {
        $minum = HistoryJadwalObatPasien::where('pasien_id', $this->id)->where('waktu', '<=', now())->where('sudah_minum', true)->count();
        $tdk_minum = HistoryJadwalObatPasien::where('pasien_id', $this->id)->where('waktu', '<=', now())->where('sudah_minum', false)->count();
        $percent = 0;
        try {
            $percent = $minum / ($minum + $tdk_minum) * 100;
        } catch (\Throwable $th) {
            //throw $th;
        }
        return ['percent' => $percent, 'minum' => $minum, 'tdk_minum' => $tdk_minum];
    }
}
