<?php

namespace App\Observers;

use App\Enums\Distribusi\StatusPengirimanBarang as S;
use App\Models\Distribusi\PengirimanBarang;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi SPPB dikirim (SPPB_DIKIRIM) ke pengaju amprah dan Karu unit tujuan.
 */
class PengirimanBarangObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PengirimanBarang $sppb */
        $sppb = $model;
        if ($sppb->status !== S::Dikirim->value) {
            return;
        }

        $r = $this->penerima();
        $ids = array_merge(
            $r->pengguna($sppb->permintaanDistribusi?->diajukan_oleh),
            $r->karuUnit((int) $sppb->unit_id),
        );

        $this->kirim($ids, 'Barang Dikirim', "{$sppb->kode} untuk {$this->nama($sppb, 'unit')} sedang dikirim dari gudang.", 'SPPB_DIKIRIM', $sppb, '/petugas-unit/penerimaan-barang');
    }
}
