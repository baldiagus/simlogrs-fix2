<?php

namespace App\Models\Pengadaan;

use App\Models\BaseModel;
use App\Models\Master\Pemasok;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PesananPembelian extends BaseModel
{
    use HasAuditLog;

    protected $table = 'pesanan_pembelian';

    protected function casts(): array
    {
        return [
            'tanggal_po' => 'date',
            'estimasi_tanggal_kirim' => 'date',
            'total_biaya' => 'decimal:2',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function pemasok(): BelongsTo
    {
        return $this->belongsTo(Pemasok::class, 'pemasok_id');
    }

    public function permintaanPengadaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanPengadaan::class, 'permintaan_pengadaan_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PesananPembelianDetail::class, 'pesanan_pembelian_id');
    }

    public function penerimaanBarang(): HasMany
    {
        return $this->hasMany(PenerimaanBarang::class, 'pesanan_pembelian_id');
    }
}
