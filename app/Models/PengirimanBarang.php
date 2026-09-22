<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengirimanBarang extends BaseModel
{
    use HasAuditLog;

    const UPDATED_AT = null;

    protected $table = 'pengiriman_barang';

    protected function casts(): array
    {
        return [
            'tanggal_kirim' => 'date',
            'dibuat_pada'   => 'datetime',
        ];
    }

    public function permintaanDistribusi(): BelongsTo
    {
        return $this->belongsTo(PermintaanDistribusi::class, 'permintaan_distribusi_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PengirimanBarangDetail::class, 'pengiriman_barang_id');
    }

    public function penerimaan(): HasMany
    {
        return $this->hasMany(PenerimaanDistribusi::class, 'pengiriman_barang_id');
    }
}
