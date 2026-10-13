<?php

namespace App\Models\Aset;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanPeminjamanDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'permintaan_peminjaman_detail';

    public function permintaanPeminjaman(): BelongsTo
    {
        return $this->belongsTo(PermintaanPeminjaman::class, 'permintaan_peminjaman_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function pengembalianDetail(): HasMany
    {
        return $this->hasMany(PengembalianPeminjamanDetail::class, 'permintaan_peminjaman_detail_id');
    }
}
