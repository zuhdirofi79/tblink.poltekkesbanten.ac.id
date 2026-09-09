<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nama_puskesmas',
        'kode_puskesmas',
        'kecamatan',
        'kotakab',
        'alamat',
        'provinsi',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
