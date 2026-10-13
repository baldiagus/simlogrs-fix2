<?php

namespace App\Models\Persediaan;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengalihanBarangDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'pengalihan_barang_detail';

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
        ];
    }

    public function pengalihanBarang(): BelongsTo
    {
        return $this->belongsTo(PengalihanBarang::class, 'pengalihan_barang_id');
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
