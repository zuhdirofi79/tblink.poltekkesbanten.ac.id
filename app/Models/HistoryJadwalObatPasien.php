<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryJadwalObatPasien extends Model
{
    public const SOURCE_SCHEDULE = 'schedule';

    public const SOURCE_MANUAL_TEST = 'manual_notification_test';

    public const SOURCE_MANUALLY_DELETED = 'manually_deleted';

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
        'source',
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
        'source' => self::SOURCE_SCHEDULE,
    ];

    public function isManualTest(): bool
    {
        return $this->source === self::SOURCE_MANUAL_TEST;
    }

    public function scopeClinical($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('source')
                ->orWhere('source', '')
                ->orWhere('source', self::SOURCE_SCHEDULE);
        });
    }

    public function scopeVisibleOnBoard($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('source')
                ->orWhere('source', '!=', self::SOURCE_MANUALLY_DELETED);
        });
    }

    public function scopeBlocksGeneration($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($clinical) {
                $clinical->whereNull('source')
                    ->orWhere('source', '')
                    ->orWhere('source', self::SOURCE_SCHEDULE);
            })->orWhere('source', self::SOURCE_MANUALLY_DELETED);
        });
    }

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

    public function hasBeenNotified(?int $userId = null): bool
    {
        $sent = $this->notifikasi_dikirim;
        if (! is_array($sent) || $sent === []) {
            return false;
        }

        $userEntries = [];
        $legacyOnly = true;
        foreach ($sent as $entry) {
            if (is_array($entry) && isset($entry['user_id'])) {
                $legacyOnly = false;
                $userEntries[] = (int) $entry['user_id'];
            }
        }

        if ($userId === null) {
            return true;
        }

        if (in_array($userId, $userEntries, true)) {
            return true;
        }

        return $legacyOnly;
    }
}
