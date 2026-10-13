<?php

namespace App\Observers;

use App\Enums\Ipsrs\StatusSpk as S;
use App\Models\Ipsrs\PenugasanMaintenance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pemicu notifikasi SPK baru (SPK_DITUGASKAN) ke teknisi. Hanya saat dibuat.
 */
class PenugasanMaintenanceObserver extends ObserverNotifikasi
{
    protected function tangani(Model $model, bool $baru): void
    {
        /** @var PenugasanMaintenance $spk */
        $spk = $model;
        // Tanpa pelaku (cron preventif) pemberitahuan SPK_PREVENTIF dikirim cron sendiri; hindari dobel.
        if (! $baru || $spk->status !== S::Ditugaskan->value || Auth::id() === null) {
            return;
        }

        $aset = $spk->loadMissing('permintaanMaintenance.aset.barang')->permintaanMaintenance?->aset?->barang?->nama ?? 'aset';

        $this->kirim($this->penerima()->pengguna($spk->teknisi_id), 'SPK Baru', "{$spk->kode}: Anda ditugaskan memperbaiki {$aset}.", 'SPK_DITUGASKAN', $spk, '/teknisi/lembar-kerja');
    }
}
