<?php

namespace App\Models\Persediaan;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenyesuaianStokDetail extends BaseModel
{
    protected $table = 'penyesuaian_stok_detail';

    protected function casts(): array
    {
        return [
            'jumlah_penyesuaian' => 'integer',
            'harga_satuan'       => 'decimal:2',
            'subtotal_nilai'     => 'decimal:2',
            'dibuat_pada'        => 'datetime',
            'diperbarui_pada'    => 'datetime',
        ];
    }

    public function penyesuaianStok(): BelongsTo
    {
        return $this->belongsTo(PenyesuaianStok::class, 'penyesuaian_stok_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function stokBatch(): BelongsTo
    {
        return $this->belongsTo(StokBatch::class, 'stok_batch_id');
    }
}
