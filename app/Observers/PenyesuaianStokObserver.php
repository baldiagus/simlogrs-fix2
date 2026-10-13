<?php

namespace App\Observers;

use App\Enums\Persediaan\StatusPenyesuaianStok as S;
use App\Models\Persediaan\PenyesuaianStok;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi penyesuaian stok menunggu otorisasi (OTORISASI_STOK).
 */
class PenyesuaianStokObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PenyesuaianStok $adj */
        $adj = $model;
        if ($adj->status !== S::Menunggu->value) {
            return;
        }

        $r = $this->penerima();
        $ids = array_merge($r->kepalaGudang((int) $adj->gudang_id), $r->adminLogistik());

        $this->kirim($ids, 'Penyesuaian Stok Menunggu', "{$adj->kode} untuk {$this->nama($adj, 'gudang')} menunggu otorisasi Anda.", 'OTORISASI_STOK', $adj, '/kepala-gudang/otorisasi-penyesuaian');
    }
}
