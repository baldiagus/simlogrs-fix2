<?php

namespace App\Models\Ipsrs;

use App\Models\Aset\Aset;
use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalMaintenance extends BaseModel
{
    use HasAuditLog;

    protected $table = 'jadwal_maintenance';

    protected function casts(): array
    {
        return [
            'interval_nilai'     => 'integer',
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

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Menghitung tanggal jatuh tempo servis berikutnya berdasarkan interval.
     */
    public function hitungTanggalBerikutnya(?Carbon $dariTanggal = null): Carbon
    {
        $base = $dariTanggal ? $dariTanggal->copy() : now();
        $nilai = max(1, (int) $this->interval_nilai);

        return match (strtoupper($this->interval_tipe ?? 'BULAN')) {
            'HARI'   => $base->addDays($nilai),
            'MINGGU' => $base->addWeeks($nilai),
            'BULAN'  => $base->addMonths($nilai),
            default  => $base->addMonths($nilai),
        };
    }

    /**
     * Label teks interval yang humanis (bebas slop).
     * Contoh: "Setiap 14 Hari", "Setiap 1 Bulan", "Setiap 3 Bulan"
     */
    public function getLabelIntervalAttribute(): string
    {
        $tipe = match (strtoupper($this->interval_tipe ?? 'BULAN')) {
            'HARI'   => 'Hari',
            'MINGGU' => 'Minggu',
            'BULAN'  => 'Bulan',
            default  => 'Bulan',
        };

        return "Setiap {$this->interval_nilai} {$tipe}";
    }
}
