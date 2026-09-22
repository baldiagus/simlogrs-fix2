<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengembalianPeminjamanDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'pengembalian_peminjaman_detail';

    public function pengembalianPeminjaman(): BelongsTo
    {
        return $this->belongsTo(PengembalianPeminjaman::class, 'pengembalian_peminjaman_id');
    }

    public function permintaanPeminjamanDetail(): BelongsTo
    {
        return $this->belongsTo(PermintaanPeminjamanDetail::class, 'permintaan_peminjaman_detail_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(PermintaanMaintenance::class, 'pengembalian_peminjaman_detail_id');
    }
}
