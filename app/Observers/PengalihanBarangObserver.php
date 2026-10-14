<?php

namespace App\Observers;

use App\Models\Persediaan\PengalihanBarang;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi pengalihan barang diajukan (PENGALIHAN_MASUK) ke penerima di lokasi tujuan.
 */
class PengalihanBarangObserver extends ObserverNotifikasi
{
    /**
     * Kirim PENGALIHAN_MASUK saat pengalihan barang berstatus DIAJUKAN ke penerima di lokasi tujuan.
     */
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PengalihanBarang $t */
        $t = $model;
        if ($t->status !== 'DIAJUKAN') {
            return;
        }

        $ids = $this->penerima()->tujuanPengalihan($t->tipe_tujuan, $t->unit_tujuan_id, $t->gudang_tujuan_id);

        $this->kirim($ids, 'Pengalihan Barang Masuk', "{$t->kode}: barang dari {$t->nama_lokasi_asal} akan dialihkan ke {$t->nama_lokasi_tujuan}.", 'PENGALIHAN_MASUK', $t, '/kepala-unit/pengalihan-aset');
    }
}
