<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenerimaanDistribusi extends BaseModel
{
    use HasAuditLog;

    protected $table = 'penerimaan_distribusi';

    protected function casts(): array
    {
        return [
            'tanggal_terima'  => 'date',
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function pengirimanBarang(): BelongsTo
    {
        return $this->belongsTo(PengirimanBarang::class, 'pengiriman_barang_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PenerimaanDistribusiDetail::class, 'penerimaan_distribusi_id');
    }
}
