<?php

namespace App\Models\Aset;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanPeminjaman extends BaseModel
{
    use HasAuditLog;

    protected $table = 'permintaan_peminjaman';

    protected function casts(): array
    {
        return [
            'tanggal_pinjam'          => 'date',
            'tanggal_kembali_rencana' => 'date',
            'dibuat_pada'             => 'datetime',
            'diperbarui_pada'         => 'datetime',
        ];
    }

    public function unitPenyedia(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_penyedia_id');
    }

    public function gudangPenyedia(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_penyedia_id');
    }

    public function unitPeminjam(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
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
        return $this->hasMany(PermintaanPeminjamanDetail::class, 'permintaan_peminjaman_id');
    }

    public function pengembalian(): HasMany
    {
        return $this->hasMany(PengembalianPeminjaman::class, 'permintaan_peminjaman_id');
    }
}
