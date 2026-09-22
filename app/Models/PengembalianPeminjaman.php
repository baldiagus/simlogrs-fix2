<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengembalianPeminjaman extends BaseModel
{
    use HasAuditLog;

    const UPDATED_AT = null;

    protected $table = 'pengembalian_peminjaman';

    protected function casts(): array
    {
        return [
            'tanggal_kembali_aktual' => 'date',
            'dibuat_pada'            => 'datetime',
        ];
    }

    public function permintaanPeminjaman(): BelongsTo
    {
        return $this->belongsTo(PermintaanPeminjaman::class, 'permintaan_peminjaman_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperiksa_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PengembalianPeminjamanDetail::class, 'pengembalian_peminjaman_id');
    }
}
