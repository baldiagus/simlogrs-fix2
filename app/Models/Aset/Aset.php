<?php

namespace App\Models\Aset;

use App\Models\BaseModel;
use App\Models\Ipsrs\PermintaanMaintenance;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use App\Models\Master\Unit;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aset extends BaseModel
{
    use HasAuditLog;

    protected $table = 'aset';

    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'nilai_perolehan' => 'decimal:2',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function getNamaAttribute(): string
    {
        return $this->barang?->nama ?? '-';
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function lokasiGudang(): BelongsTo
    {
        return $this->belongsTo(LokasiGudang::class, 'lokasi_gudang_id');
    }

    public function permintaanMaintenance(): HasMany
    {
        return $this->hasMany(PermintaanMaintenance::class, 'aset_id');
    }

    public function peminjamanDetail(): HasMany
    {
        return $this->hasMany(PermintaanPeminjamanDetail::class, 'aset_id');
    }

    public function pengalihanDetail(): HasMany
    {
        return $this->hasMany(PengalihanAsetDetail::class, 'aset_id');
    }

    /**
     * Mengambil riwayat kronologis sirkulasi peminjaman dan pengembalian fisik aset.
     */
    public function riwayatPeminjaman()
    {
        return \App\Models\Aset\PermintaanPeminjamanDetail::where('aset_id', $this->id)
            ->with([
                'permintaanPeminjaman.unitPeminjam',
                'permintaanPeminjaman.pengaju',
                'pengembalianDetail.pengembalianPeminjaman.pemeriksa',
            ])
            ->whereHas('permintaanPeminjaman')
            ->get()
            ->sortByDesc(fn ($d) => $d->permintaanPeminjaman?->id)
            ->map(function ($d) {
                $pinjam = $d->permintaanPeminjaman;
                $kembali = $d->pengembalianDetail->first();
                $pengembalianHeader = $kembali?->pengembalianPeminjaman;

                return [
                    'id'                      => $d->id,
                    'kode_peminjaman'         => $pinjam?->kode ?? '-',
                    'unit_peminjam'           => $pinjam?->unitPeminjam?->nama ?? 'Ruangan',
                    'peminjam'                => $pinjam?->pengaju?->nama ?? '-',
                    'tanggal_pinjam'          => $pinjam?->tanggal_pinjam?->format('Y-m-d') ?? '-',
                    'tanggal_kembali_rencana' => $pinjam?->tanggal_kembali_rencana?->format('Y-m-d') ?? '-',
                    'tanggal_kembali_aktual'  => $pengembalianHeader?->tanggal_kembali_aktual?->format('Y-m-d'),
                    'status'                  => $pinjam?->status ?? '-',
                    'keperluan'               => $pinjam?->keperluan ?? '-',
                    'kondisi_saat_pinjam'     => $d->kondisi_saat_pinjam ?? 'BAIK',
                    'kondisi_saat_kembali'    => $kembali?->kondisi_saat_kembali,
                    'catatan_pengembalian'    => $kembali?->catatan,
                    'pemeriksa_kembali'       => $pengembalianHeader?->pemeriksa?->nama,
                ];
            })
            ->values();
    }

    /**
     * Mengambil riwayat kronologis pengalihan dan mutasi fisik permanen aset.
     */
    public function riwayatPengalihan()
    {
        return \App\Models\Aset\PengalihanAsetDetail::where('aset_id', $this->id)
            ->with([
                'pengalihanAset.unitAsal',
                'pengalihanAset.gudangAsal',
                'pengalihanAset.unitTujuan',
                'pengalihanAset.gudangTujuan',
                'pengalihanAset.pengaju',
                'pengalihanAset.penerima',
            ])
            ->whereHas('pengalihanAset', fn ($q) => $q->where('status', 'SELESAI'))
            ->get()
            ->sortByDesc(fn ($d) => $d->pengalihanAset?->tanggal_serah_terima ?? $d->pengalihanAset?->dibuat_pada)
            ->map(function ($d) {
                $p = $d->pengalihanAset;
                return [
                    'id'                   => $d->id,
                    'pengalihan_id'        => $p?->id,
                    'kode_pengalihan'      => $p?->kode ?? '-',
                    'asal_nama'            => $p?->nama_lokasi_asal ?? '-',
                    'tujuan_nama'          => $p?->nama_lokasi_tujuan ?? '-',
                    'tanggal_pengalihan'   => $p?->tanggal_pengalihan?->format('Y-m-d') ?? '-',
                    'tanggal_serah_terima' => $p?->tanggal_serah_terima?->format('Y-m-d H:i') ?? ($p?->tanggal_serah_terima?->format('Y-m-d') ?? '-'),
                    'status'               => $p?->status ?? 'SELESAI',
                    'alasan'               => $p?->alasan ?? '-',
                    'kondisi_sebelum'      => $d->kondisi_sebelum ?? 'BAIK',
                    'kondisi_saat_diterima'=> $d->kondisi_saat_diterima ?? 'BAIK',
                    'pengaju'              => $p?->pengaju?->nama ?? '-',
                    'penerima'             => $p?->penerima?->nama ?? '-',
                    'catatan'              => $d->catatan ?: $p?->catatan,
                ];
            })
            ->values();
    }
}
