<?php

namespace App\Models\Ipsrs;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'log_maintenance';

    protected function casts(): array
    {
        return [
            'tanggal_pengerjaan' => 'datetime',
            'dibuat_pada'        => 'datetime',
            'diperbarui_pada'    => 'datetime',
        ];
    }

    public function permintaanMaintenance(): BelongsTo
    {
        return $this->belongsTo(PermintaanMaintenance::class, 'permintaan_maintenance_id');
    }

    public function penugasanMaintenance(): BelongsTo
    {
        return $this->belongsTo(PenugasanMaintenance::class, 'penugasan_maintenance_id');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikerjakan_oleh');
    }

    public function sukuCadang(): HasMany
    {
        return $this->hasMany(SukuCadangMaintenance::class, 'log_maintenance_id');
    }
}
