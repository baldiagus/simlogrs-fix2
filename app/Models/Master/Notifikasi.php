<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi inbox pengguna (sumber utama; push FCM hanya pengiriman tambahan).
 * Kolom: judul, pesan, tipe, referensi_id, tautan, dibaca (lihat docs/NOTIFICATION_CONTRACT.md).
 */
class Notifikasi extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'notifikasi';

    protected $fillable = [
        'pengguna_id', 'judul', 'pesan', 'tipe', 'referensi_id', 'tautan', 'dibaca', 'dibuat_pada',
    ];

    protected function casts(): array
    {
        return [
            'dibaca'      => 'boolean',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    /**
     * Hanya notifikasi yang belum dibaca.
     */
    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('dibaca', false);
    }
}
