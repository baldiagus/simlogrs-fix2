<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AnggaranPengadaan extends BaseModel
{
    use HasAuditLog;

    protected $table = 'anggaran_pengadaan';

    protected function casts(): array
    {
        return [
            'pagu' => 'decimal:2',
            'realisasi' => 'decimal:2',
            'sisa' => 'decimal:2',
            'serapan' => 'decimal:2',
            'po_terkait' => 'array',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
