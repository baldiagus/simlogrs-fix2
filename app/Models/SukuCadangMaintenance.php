<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SukuCadangMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'suku_cadang_maintenance';

    protected function casts(): array
    {
        return [
            'jumlah_digunakan' => 'integer',
            'harga_satuan'     => 'decimal:2',
            'subtotal'         => 'decimal:2',
            'dibuat_pada'      => 'datetime',
            'diperbarui_pada'  => 'datetime',
        ];
    }

    public function permintaanMaintenance(): BelongsTo
    {
        return $this->belongsTo(PermintaanMaintenance::class, 'permintaan_maintenance_id');
    }

    public function logMaintenance(): BelongsTo
    {
        return $this->belongsTo(LogMaintenance::class, 'log_maintenance_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function stokBatch(): BelongsTo
    {
        return $this->belongsTo(StokBatch::class, 'stok_batch_id');
    }

    public function pergerakanStok(): BelongsTo
    {
        return $this->belongsTo(PergerakanStok::class, 'pergerakan_stok_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
