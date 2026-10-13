<?php

namespace App\Models\Pengadaan;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenerimaanBarang extends BaseModel
{
    use HasAuditLog;

    protected $table = 'penerimaan_barang';

    protected function casts(): array
    {
        return [
            'tanggal_terima' => 'date',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function pesananPembelian(): BelongsTo
    {
        return $this->belongsTo(PesananPembelian::class, 'pesanan_pembelian_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PenerimaanBarangDetail::class, 'penerimaan_barang_id');
    }
}
