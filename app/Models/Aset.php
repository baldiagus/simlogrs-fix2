<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aset extends BaseModel
{
    use HasAuditLog;

    protected $table = 'aset';

    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'nilai_perolehan' => 'decimal:2',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function lokasiGudang(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_id');
    }

    public function permintaanMaintenance(): HasMany
    {
        return $this->hasMany(PermintaanMaintenance::class, 'aset_id');
    }

    public function peminjamanDetail(): HasMany
    {
        return $this->hasMany(PermintaanPeminjamanDetail::class, 'aset_id');
    }
}
