<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'permintaan_maintenance';

    protected function casts(): array
    {
        return [
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function pengembalianPeminjamanDetail(): BelongsTo
    {
        return $this->belongsTo(PengembalianPeminjamanDetail::class, 'pengembalian_peminjaman_detail_id');
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilaporkan_oleh');
    }
}
