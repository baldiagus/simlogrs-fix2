<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameDetail extends BaseModel
{
    protected $table = 'stok_opname_detail';

    protected function casts(): array
    {
        return [
            'jumlah_sistem'   => 'integer',
            'jumlah_fisik'    => 'integer',
            'selisih'         => 'integer',
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'stok_opname_id');
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
