<?php

namespace App\Models\Persediaan;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PergerakanStok extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'pergerakan_stok';

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'saldo_sebelum' => 'integer',
            'saldo_sesudah' => 'integer',
            'referensi_id' => 'integer',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function stokBatch(): BelongsTo
    {
        return $this->belongsTo(StokBatch::class, 'stok_batch_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function lokasiGudang(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_id');
    }

    public function pembuatRecord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
