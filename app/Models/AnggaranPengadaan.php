<?php

namespace App\Models;

use App\Traits\HasAuditLog;

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
}
