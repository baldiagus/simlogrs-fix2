<?php

namespace App\Models\Distribusi;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use App\Models\Master\Unit;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokRuangan extends BaseModel
{
    use HasAuditLog;

    protected $table = 'stok_ruangan';

    protected function casts(): array
    {
        return [
            'jumlah'        => 'integer',
            'stok_minimum'  => 'integer',
            'stok_maksimum' => 'integer',
            'dibuat_pada'   => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
