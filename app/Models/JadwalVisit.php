<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalVisit extends Model
{
    /** @use HasFactory<\Database\Factories\JadwalVisitFactory> */
    use HasFactory;
    protected $fillable = [
        'pasien_id',
        'admin_id',
        'tgl_visit',
        'keluhan',
        'catatan',
        'active',
        'notification_sent_at',
    ];
    protected $casts = [
        'tgl_visit' => 'datetime',
        'active' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];
    protected $attributes = [
        'catatan' => '-',
        'keluhan' => 'Tidak ada keluhan',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'pasien_id');
    }
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
