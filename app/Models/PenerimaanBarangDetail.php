<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanBarangDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'penerimaan_barang_detail';

    protected function casts(): array
    {
        return [
            'tanggal_kedaluwarsa' => 'date',
            'jumlah_diterima' => 'integer',
            'jumlah_rusak' => 'integer',
            'harga_satuan' => 'decimal:2',
        ];
    }

    public function penerimaanBarang(): BelongsTo
    {
        return $this->belongsTo(PenerimaanBarang::class, 'penerimaan_barang_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function lokasiGudang(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_id');
    }
}
