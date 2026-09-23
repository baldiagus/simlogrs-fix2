<?php

namespace App\Models;

use App\Traits\HasAuditLog;

class PengaturanSistem extends BaseModel
{
    use HasAuditLog;

    protected $table = 'pengaturan_sistem';

    protected function casts(): array
    {
        return [
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }
}
