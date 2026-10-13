<?php

namespace App\Observers;

use App\Enums\Aset\StatusPeminjaman as S;
use App\Models\Aset\PermintaanPeminjaman;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi peminjaman aset: pengajuan (PINJAM_BARU) ke pemilik aset dan keputusan (PINJAM_DIPUTUSKAN) ke pengaju.
 */
class PermintaanPeminjamanObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PermintaanPeminjaman $p */
        $p = $model;
        $r = $this->penerima();

        if ($p->status === S::Diajukan->value) {
            // Pemilik aset: Karu unit penyedia (RUANGAN) atau kepala gudang penyedia (GUDANG), sesuai antrean otorisasi.
            $ids = $p->tipe_penyedia === 'GUDANG'
                ? $r->kepalaGudang((int) $p->gudang_penyedia_id)
                : $r->karuUnit((int) $p->unit_penyedia_id);
            $this->kirim($ids, 'Permintaan Peminjaman Aset', "{$p->kode} dari {$this->nama($p, 'unitPeminjam')} menunggu otorisasi Anda.", 'PINJAM_BARU', $p, '/kepala-unit/approval-peminjaman');
        } elseif ($p->status === S::Disetujui->value || $p->status === S::Ditolak->value) {
            $hasil = $p->status === S::Disetujui->value ? 'disetujui' : 'ditolak';
            $this->kirim($r->pengguna($p->diajukan_oleh), "Peminjaman {$hasil}", "{$p->kode} Anda {$hasil}.", 'PINJAM_DIPUTUSKAN', $p, '/petugas-unit/peminjaman-aset');
        }
    }
}
