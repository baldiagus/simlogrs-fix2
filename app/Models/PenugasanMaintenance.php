<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenugasanMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'penugasan_maintenance';

    protected function casts(): array
    {
        return [
            'tanggal_penugasan' => 'date',
            'target_selesai'    => 'date',
            'dibuat_pada'       => 'datetime',
            'diperbarui_pada'   => 'datetime',
        ];
    }

    public function permintaanMaintenance(): BelongsTo
    {
        return $this->belongsTo(PermintaanMaintenance::class, 'permintaan_maintenance_id');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function penugast(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    public function logMaintenance(): HasMany
    {
        return $this->hasMany(LogMaintenance::class, 'penugasan_maintenance_id');
    }
}
