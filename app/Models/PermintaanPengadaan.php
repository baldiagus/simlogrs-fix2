<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanPengadaan extends BaseModel
{
    use HasAuditLog;

    protected $table = 'permintaan_pengadaan';

    protected function casts(): array
    {
        return [
            'tanggal_permintaan' => 'date',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function getNamaPemohonAttribute(): string
    {
        return $this->unit ? $this->unit->nama : ($this->gudang ? $this->gudang->nama : '-');
    }

    public function getTipePemohonAttribute(): string
    {
        return $this->unit_id ? 'UNIT' : ($this->gudang_id ? 'GUDANG' : '-');
    }


    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PermintaanPengadaanDetail::class, 'permintaan_pengadaan_id');
    }

    public function pesananPembelian(): HasMany
    {
        return $this->hasMany(PesananPembelian::class, 'permintaan_pengadaan_id');
    }
}
