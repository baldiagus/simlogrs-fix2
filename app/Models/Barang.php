<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends BaseModel
{
    use HasAuditLog;

    protected $table = 'barang';

    protected function casts(): array
    {
        return [
            'harga_beli_terakhir' => 'decimal:2',
            'harga_estimasi' => 'decimal:2',
            'stok_minimum' => 'integer',
            'stok_maksimum' => 'integer',
            'titik_pesan' => 'integer',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'kategori_id');
    }

    public function satuan(): BelongsTo
    {
        return $this->belongsTo(SatuanBarang::class, 'satuan_id');
    }

    public function merek(): BelongsTo
    {
        return $this->belongsTo(MerekBarang::class, 'merek_id');
    }

    public function pemasokDefault(): BelongsTo
    {
        return $this->belongsTo(Pemasok::class, 'pemasok_default_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'barang_id');
    }
}
