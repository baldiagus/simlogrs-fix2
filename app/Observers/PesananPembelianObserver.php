<?php

namespace App\Observers;

use App\Enums\Pengadaan\StatusPesananPembelian as S;
use App\Models\Pengadaan\PesananPembelian;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi PO terbit (PO_TERBIT) ke staf gudang penerima.
 */
class PesananPembelianObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PesananPembelian $po */
        $po = $model;
        if ($po->status !== S::Diterbitkan->value) {
            return;
        }

        // PO tidak punya gudang_id: pakai gudang pemohon PR; bila PR berasal dari unit, gudang dipilih saat penerimaan.
        $gudangId = $po->permintaanPengadaan?->gudang_id;
        $r = $this->penerima();
        $ids = $gudangId ? $r->gudangStaf($gudangId) : $r->semuaGudangStaf();

        $this->kirim($ids, 'PO Terbit', "{$po->kode} terbit dan menunggu penerimaan barang.", 'PO_TERBIT', $po, '/petugas-gudang/penerimaan-po');
    }
}
