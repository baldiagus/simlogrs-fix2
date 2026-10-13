<?php

namespace App\Observers;

use App\Enums\Pengadaan\StatusPermintaanPengadaan as S;
use App\Models\Pengadaan\PermintaanPengadaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi PR: pengajuan (APPROVAL_PR), diteruskan ke pengadaan, dan keputusan (PR_DIPUTUSKAN).
 */
class PermintaanPengadaanObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PermintaanPengadaan $pr */
        $pr = $model;
        $pemohon = $this->nama($pr, $pr->unit_id ? 'unit' : 'gudang');
        $r = $this->penerima();

        if ($pr->status === S::Diajukan->value) {
            // Karu unit / kepala gudang pemohon + admin logistik (visibilitas antreanPr).
            $ids = array_merge(
                $pr->unit_id ? $r->karuUnit($pr->unit_id) : [],
                $pr->gudang_id ? $r->kepalaGudang($pr->gudang_id) : [],
                $r->adminLogistik(),
            );
            $this->kirim($ids, 'Pengajuan PR Baru', "{$pr->kode} dari {$pemohon} menunggu persetujuan Anda.", 'APPROVAL_PR', $pr, '/kepala-unit/permintaan-pengadaan');

            return;
        }

        if ($pr->status === S::DiteruskanKePengadaan->value) {
            $this->kirim($r->role('pengadaan'), 'PR Diteruskan ke Pengadaan', "{$pr->kode} dari {$pemohon} siap diproses menjadi PO.", 'APPROVAL_PR', $pr, '/pengadaan/permintaan-pengadaan');

            // Persetujuan ruangan/gudang (DIAJUKAN -> DITERUSKAN) juga merupakan keputusan bagi pengaju.
            if ($this->statusLama($pr, $baru) === S::Diajukan->value) {
                $this->kirimKeputusan($pr, 'disetujui');
            }

            return;
        }

        if ($pr->status === S::Disetujui->value) {
            $this->kirimKeputusan($pr, 'disetujui');
        } elseif ($pr->status === S::Ditolak->value) {
            $this->kirimKeputusan($pr, 'ditolak');
        }
    }

    private function kirimKeputusan(PermintaanPengadaan $pr, string $hasil): void
    {
        $this->kirim($this->penerima()->pengguna($pr->diajukan_oleh), "PR {$hasil}", "{$pr->kode} Anda {$hasil}.", 'PR_DIPUTUSKAN', $pr, '/petugas-unit/permintaan-pengadaan');
    }
}
