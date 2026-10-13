<?php

namespace App\Models\Ipsrs;

use App\Models\Aset\Aset;
use App\Models\Aset\PengembalianPeminjamanDetail;
use App\Models\BaseModel;
use App\Models\Master\Unit;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanMaintenance::class, 'permintaan_maintenance_id');
    }

    public function penugasanAktif(): HasOne
    {
        return $this->hasOne(PenugasanMaintenance::class, 'permintaan_maintenance_id')->latestOfMany();
    }

    public function logMaintenance(): HasMany
    {
        return $this->hasMany(LogMaintenance::class, 'permintaan_maintenance_id');
    }

    public function sukuCadang(): HasMany
    {
        return $this->hasMany(SukuCadangMaintenance::class, 'permintaan_maintenance_id');
    }
}
