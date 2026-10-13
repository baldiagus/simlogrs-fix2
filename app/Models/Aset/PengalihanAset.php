<?php

namespace App\Models\Aset;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengalihanAset extends BaseModel
{
    use HasAuditLog;

    protected $table = 'pengalihan_aset';

    protected function casts(): array
    {
        return [
            'tanggal_pengalihan' => 'date',
            'tanggal_serah_terima' => 'datetime',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function unitAsal(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_asal_id');
    }

    public function gudangAsal(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_asal_id');
    }

    public function unitTujuan(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_tujuan_id');
    }

    public function gudangTujuan(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_tujuan_id');
    }

    public function lokasiGudangTujuan(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_tujuan_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(PengalihanAsetDetail::class, 'pengalihan_aset_id');
    }

    /**
     * Helper nama lokasi asal (Ruangan atau Gudang).
     */
    public function getNamaLokasiAsalAttribute(): string
    {
        return $this->tipe_asal === 'RUANGAN'
            ? ($this->unitAsal?->nama ?? 'Ruangan Tidak Diketahui')
            : ($this->gudangAsal?->nama ?? 'Gudang Tidak Diketahui');
    }

    /**
     * Helper nama lokasi tujuan (Ruangan atau Gudang).
     */
    public function getNamaLokasiTujuanAttribute(): string
    {
        return $this->tipe_tujuan === 'RUANGAN'
            ? ($this->unitTujuan?->nama ?? 'Ruangan Tidak Diketahui')
            : ($this->gudangTujuan?->nama ?? 'Gudang Tidak Diketahui');
    }
}
