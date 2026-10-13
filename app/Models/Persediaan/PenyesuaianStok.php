<?php

namespace App\Models\Persediaan;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenyesuaianStok extends BaseModel
{
    use HasAuditLog;

    protected $table = 'penyesuaian_stok';

    protected function casts(): array
    {
        return [
            'tanggal_penyesuaian' => 'date',
            'dibuat_pada'         => 'datetime',
            'diperbarui_pada'     => 'datetime',
        ];
    }

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'stok_opname_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
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
        return $this->hasMany(PenyesuaianStokDetail::class, 'penyesuaian_stok_id');
    }
}
