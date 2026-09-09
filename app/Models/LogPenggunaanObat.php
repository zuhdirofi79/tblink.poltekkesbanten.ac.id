<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogPenggunaanObat extends Model
{
    protected $fillable = [
        'jadwal_obat_id',
        'jadwal_minum_obat',
        'aktual_minum_obat',
        'dosis_obat',
        'satuan',
        'catatan',
        'user_id',
    ];
    protected $attributes = [
        'catatan' => '-',
    ];

    public function jadwal()
    {
        return $this->belongsTo(JadwalObat::class, 'jadwal_obat_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            $model->jadwal->op->stock < 1 ? $model->jadwal->op->update(['active' => false]) : null;
        });
    }
}
