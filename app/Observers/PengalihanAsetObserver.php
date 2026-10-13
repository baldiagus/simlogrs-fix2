<?php

namespace App\Observers;

use App\Enums\Aset\StatusPengalihan as S;
use App\Models\Aset\PengalihanAset;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi pengalihan aset diajukan (PENGALIHAN_MASUK) ke pimpinan fasilitas tujuan.
 */
class PengalihanAsetObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PengalihanAset $t */
        $t = $model;
        if ($t->status !== S::Diajukan->value) {
            return;
        }

        $r = $this->penerima();
        $ids = $t->tipe_tujuan === 'GUDANG' ? $r->kepalaGudang((int) $t->gudang_tujuan_id) : $r->karuUnit((int) $t->unit_tujuan_id);

        $this->kirim($ids, 'Pengalihan Aset Masuk', "{$t->kode}: aset dari {$t->nama_lokasi_asal} akan dialihkan ke {$t->nama_lokasi_tujuan}.", 'PENGALIHAN_MASUK', $t, '/kepala-unit/pengalihan-aset');
    }
}
