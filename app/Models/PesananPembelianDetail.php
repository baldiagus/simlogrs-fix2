<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesananPembelianDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'pesanan_pembelian_detail';

    protected function casts(): array
    {
        return [
            'jumlah_dipesan' => 'integer',
            'jumlah_diterima' => 'integer',
            'harga_satuan' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'pesanan_pembelian_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
