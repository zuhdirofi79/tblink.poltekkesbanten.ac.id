<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryJadwalObatPasien extends Model
{
    protected $fillable = [
        'pasien_id',
        'obat_id',
        'detail_jadwal_obat_id',
        'waktu',
        'dosis',
        'satuan',
        'sudah_minum',
        'waktu_aktual_minum',
        'notifikasi_dikirim',
    ];
    protected $casts = [
        'notifikasi_dikirim' => 'array',
        'waktu' => 'datetime',
        'sudah_minum' => 'boolean',
        'waktu_aktual_minum' => 'datetime',
    ];
    protected $attributes = [
        'sudah_minum' => false,
        'notifikasi_dikirim' => '[]',
    ];

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'obat_id');
    }

    public function detailJadwalObat()
    {
        return $this->belongsTo(DetailJadwalObat::class);
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'pasien_id');
    }

    public function hasBeenNotified(): bool
    {
        $sent = $this->notifikasi_dikirim;

        return is_array($sent) && count($sent) > 0;
    }
}
