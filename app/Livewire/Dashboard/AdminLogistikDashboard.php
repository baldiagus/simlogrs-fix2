<?php

namespace App\Livewire\Dashboard;

use App\Enums\Distribusi\StatusPengirimanBarang;
use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Persediaan\PengalihanBarang;
use App\Models\Persediaan\StokBatch;
use Livewire\Component;

class AdminLogistikDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_AdminLogistikDashboard'), 403);

        // 1. Antrean Otorisasi Distribusi (Meja 2 - Menunggu Verifikasi Gudang/Admin)
        $antreanOtorisasi = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value)
            ->with(['unit', 'pengaju', 'detail.barang'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAntreanOtorisasi = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::DisetujuiKaru->value)->count();

        // 2. SPPB Sedang Dalam Pengiriman
        $totalSppbDikirim = PengirimanBarang::where('status', StatusPengirimanBarang::Dikirim->value)->count();

        // 3. Batch Stok Mendekati Kedaluwarsa Sentral (<= 90 hari)
        $batchKritis = StokBatch::with(['barang', 'gudang'])
            ->where('status', 'AKTIF')
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(90)->toDateString())
            ->orderBy('tanggal_kedaluwarsa')
            ->take(5)
            ->get();
        $totalBatchKritis = StokBatch::where('status', 'AKTIF')
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(90)->toDateString())
            ->count();

        // 4. Mutasi / Pengalihan Barang Antar Gudang Aktif
        $totalPengalihanAktif = PengalihanBarang::whereIn('status', ['DRAFT', 'DIAJUKAN'])->count();

        return view('livewire.dashboard.admin-logistik-dashboard', [
            'totalAntreanOtorisasi' => $totalAntreanOtorisasi,
            'antreanOtorisasi'       => $antreanOtorisasi,
            'totalSppbDikirim'      => $totalSppbDikirim,
            'totalBatchKritis'      => $totalBatchKritis,
            'batchKritis'           => $batchKritis,
            'totalPengalihanAktif'  => $totalPengalihanAktif,
        ])->layout('layouts.portal', ['portal' => 'admin-logistik', 'title' => 'Dashboard Admin Logistik']);
    }
}
