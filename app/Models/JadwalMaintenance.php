<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'jadwal_maintenance';

    protected function casts(): array
    {
        return [
            'tanggal_terakhir'   => 'date',
            'tanggal_berikutnya' => 'date',
            'dibuat_pada'        => 'datetime',
            'diperbarui_pada'    => 'datetime',
        ];
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
