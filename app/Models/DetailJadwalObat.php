<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailJadwalObat extends Model
{
    protected $fillable = [
        'jadwal_obat_id',
        'waktu',
        'dosis',
        'satuan',
    ];
    /**
     * Get the jadwalObat  that owns the DetailJadwalObat
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function jadwalObat(): BelongsTo
    {
        return $this->belongsTo(JadwalObat::class, 'jadwal_obat_id');
    }
}
