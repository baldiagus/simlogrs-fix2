<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StokOpname extends BaseModel
{
    use HasAuditLog;

    protected $table = 'stok_opname';

    protected function casts(): array
    {
        return [
            'tanggal_opname'  => 'date',
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(StokOpnameDetail::class, 'stok_opname_id');
    }

    public function penyesuaian(): HasOne
    {
        return $this->hasOne(PenyesuaianStok::class, 'stok_opname_id');
    }
}
