<?php

namespace App\Models\Distribusi;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use App\Models\Persediaan\StokBatch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengirimanBarangDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'pengiriman_barang_detail';

    protected function casts(): array
    {
        return [
            'jumlah_dikirim' => 'integer',
        ];
    }

    public function pengirimanBarang(): BelongsTo
    {
        return $this->belongsTo(PengirimanBarang::class, 'pengiriman_barang_id');
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
