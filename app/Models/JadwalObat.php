<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalObat extends Model
{
    /** @use HasFactory<\Database\Factories\JadwalObatFactory> */
    use HasFactory;
    protected $fillable = [
        'obat_pasien_id',
        'obat_id',
        'pasien_id',
        'mulai_tanggal',
        'selesai_tanggal',
        'qty_diberikan',
        'satuan',
    ];
    /**
     * Get all of the details for the JadwalObat
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function details(): HasMany
    {
        return $this->hasMany(DetailJadwalObat::class, 'jadwal_obat_id');
    }
    public function op()
    {
        return $this->belongsTo(ObatPasien::class, 'obat_pasien_id');
    }
    /**
     * Get all of the logs for the JadwalObat
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function logs(): HasMany
    {
        return $this->hasMany(LogPenggunaanObat::class);
    }
}
