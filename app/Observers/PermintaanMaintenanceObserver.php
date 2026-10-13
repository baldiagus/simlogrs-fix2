<?php

namespace App\Observers;

use App\Enums\Ipsrs\StatusMaintenance as S;
use App\Models\Ipsrs\PermintaanMaintenance;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi tiket maintenance: tiket baru (TIKET_BARU) dan perbaikan selesai (PERBAIKAN_SELESAI).
 */
class PermintaanMaintenanceObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PermintaanMaintenance $t */
        $t = $model;
        $r = $this->penerima();
        $aset = $t->loadMissing('aset.barang')->aset?->barang?->nama ?? 'aset';

        if ($t->status === S::Dilaporkan->value) {
            $this->kirim(array_merge($r->kepalaTeknisi(), $r->adminLogistik()), 'Tiket Perbaikan Baru', "{$t->kode}: {$aset} dilaporkan bermasalah.", 'TIKET_BARU', $t, '/teknisi/spk');
        } elseif ($t->status === S::Selesai->value) {
            $ids = array_merge($r->pengguna($t->dilaporkan_oleh), $t->unit_id ? $r->karuUnit($t->unit_id) : []);
            $this->kirim($ids, 'Perbaikan Selesai', "{$t->kode}: perbaikan {$aset} telah selesai.", 'PERBAIKAN_SELESAI', $t, '/petugas-unit/lapor-servis');
        }
    }
}
