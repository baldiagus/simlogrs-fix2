<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokBatch extends BaseModel
{
    use HasAuditLog;

    protected $table = 'stok_batch';

    protected function casts(): array
    {
        return [
            'tanggal_kedaluwarsa' => 'date',
            'jumlah' => 'integer',
            'jumlah_dipesan' => 'integer',
            'harga_satuan' => 'decimal:2',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function lokasiGudang(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_id');
    }

    public function pergerakanStok(): HasMany
    {
        return $this->hasMany(PergerakanStok::class, 'stok_batch_id');
    }
}
