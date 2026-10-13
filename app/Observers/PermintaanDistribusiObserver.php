<?php

namespace App\Observers;

use App\Enums\Distribusi\StatusPermintaanDistribusi as S;
use App\Models\Distribusi\PermintaanDistribusi;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi amprah: approval Karu (APPROVAL_AMPRAH), antrean picking (ANTREAN_PICKING, ke gudang tujuan), penolakan (AMPRAH_DIPUTUSKAN).
 */
class PermintaanDistribusiObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PermintaanDistribusi $a */
        $a = $model;
        $unit = $this->nama($a, 'unit');
        $r = $this->penerima();

        match ($a->status) {
            S::Diajukan->value => $this->kirim($r->karuUnit((int) $a->unit_id), 'Amprah Baru', "{$a->kode} dari {$unit} menunggu persetujuan Anda.", 'APPROVAL_AMPRAH', $a, '/kepala-unit/approval-permintaan'),
            S::DisetujuiKaru->value => $this->kirim($r->gudangStaf((int) $a->gudang_id), 'Antrean Picking', "{$a->kode} untuk {$unit} siap diproses gudang.", 'ANTREAN_PICKING', $a, '/petugas-gudang/picking-distribusi'),
            S::Ditolak->value => $this->kirim($r->pengguna($a->diajukan_oleh), 'Amprah Ditolak', "{$a->kode} untuk {$unit} ditolak.", 'AMPRAH_DIPUTUSKAN', $a, '/petugas-unit/permintaan-barang'),
            default => null,
        };
    }
}
