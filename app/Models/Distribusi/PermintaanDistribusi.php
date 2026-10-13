<?php

namespace App\Models\Distribusi;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermintaanDistribusi extends BaseModel
{
    use HasAuditLog;

    protected $table = 'permintaan_distribusi';

    protected function casts(): array
    {
        return [
            'tanggal_permintaan' => 'date',
            'dibuat_pada'        => 'datetime',
            'diperbarui_pada'    => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
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
        return $this->hasMany(PermintaanDistribusiDetail::class, 'permintaan_distribusi_id');
    }

    public function pengiriman(): HasMany
    {
        return $this->hasMany(PengirimanBarang::class, 'permintaan_distribusi_id');
    }

    /** Kuota disetujui per barang (jumlah_disetujui, fallback jumlah), digabung bila barang muncul di beberapa baris. */
    protected function kuotaPerBarang(): array
    {
        return $this->detail
            ->groupBy('barang_id')
            ->map(fn ($rows) => (int) $rows->sum(fn ($d) => $d->jumlah_disetujui !== null ? $d->jumlah_disetujui : $d->jumlah))
            ->all();
    }

    /** Setiap barang terpenuhi: kelebihan di satu barang tidak boleh menutup kekurangan barang lain. */
    protected function semuaBarangTerpenuhi(array $realisasiPerBarang): bool
    {
        foreach ($this->kuotaPerBarang() as $barangId => $kuota) {
            if ((int) ($realisasiPerBarang[$barangId] ?? 0) < $kuota) {
                return false;
            }
        }

        return true;
    }

    public function semuaBarangDikirim(): bool
    {
        $dikirim = PengirimanBarangDetail::query()
            ->whereHas('pengirimanBarang', fn ($q) => $q
                ->where('permintaan_distribusi_id', $this->id)
                ->where('status', '!=', \App\Enums\Distribusi\StatusPengirimanBarang::Batal->value))
            ->selectRaw('barang_id, sum(jumlah_dikirim) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->all();

        return $this->semuaBarangTerpenuhi($dikirim);
    }

    public function semuaBarangDiterima(): bool
    {
        $diterima = PenerimaanDistribusiDetail::query()
            ->whereHas('penerimaanDistribusi.pengirimanBarang', fn ($q) => $q->where('permintaan_distribusi_id', $this->id))
            ->selectRaw('barang_id, sum(jumlah_diterima) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->all();

        return $this->semuaBarangTerpenuhi($diterima);
    }
}
