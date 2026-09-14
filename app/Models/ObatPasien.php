<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ObatPasien extends Model
{
    /** @use HasFactory<\Database\Factories\ObatPasienFactory> */
    use HasFactory;
    protected $fillable = [
        'pasien_id',
        'obat_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = ['stock'];

    public function getStockAttribute()
    {
        $diberikan = $this->jadwal()->sum('qty_diberikan');
        $konsumsi = $this->jadwal->logs()->sum('dosis_obat');
        return $diberikan - $konsumsi;
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }
    /**
     * Get the jadwal associated with the ObatPasien
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function jadwal(): HasOne
    {
        return $this->hasOne(JadwalObat::class, 'obat_pasien_id');
    }
}
