<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeTb extends Model
{
    protected $fillable = [
        'tipe_tb',
    ];

    public function pasien()
    {
        return $this->hasMany(Pasien::class, 'tipe_tb', 'tipe_tb');
    }
}
