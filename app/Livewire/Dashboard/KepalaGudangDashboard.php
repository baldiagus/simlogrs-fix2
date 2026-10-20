<?php

namespace App\Livewire\Dashboard;

use App\Enums\Persediaan\StatusStokOpname;
use App\Models\Persediaan\PenyesuaianStok;
use App\Models\Persediaan\StokBatch;
use App\Models\Persediaan\StokOpname;
use Livewire\Component;

class KepalaGudangDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_KepalaGudangDashboard'), 403);

        $gudangId = auth()->user()?->gudang_id;

        // 1. Batch Kedaluwarsa Kritis (<= 90 Hari)
        $batchKritis = StokBatch::with(['barang', 'gudang'])
            ->where('status', 'AKTIF')
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(90)->toDateString())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->orderBy('tanggal_kedaluwarsa')
            ->take(5)
            ->get();
        $totalBatchKritis = StokBatch::where('status', 'AKTIF')
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(90)->toDateString())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        // 2. Sesi Stock Opname Berjalan
        $opnameBerjalan = StokOpname::with('gudang')
            ->whereIn('status', StatusStokOpname::aktif())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->latest('id')
            ->take(5)
            ->get();
        $totalOpnameBerjalan = StokOpname::whereIn('status', StatusStokOpname::aktif())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        // 3. Menunggu Otorisasi Penyesuaian Varians Opname
        $penyesuaianMenunggu = PenyesuaianStok::with(['gudang', 'pengaju'])
            ->where('status', 'MENUNGGU')
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->latest('id')
            ->take(5)
            ->get();
        $totalPenyesuaianMenunggu = PenyesuaianStok::where('status', 'MENUNGGU')
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        // 4. Total Batch Aktif Terdaftar
        $totalBatchAktif = StokBatch::where('status', 'AKTIF')
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        return view('livewire.dashboard.kepala-gudang-dashboard', [
            'totalBatchKritis'        => $totalBatchKritis,
            'batchKritis'             => $batchKritis,
            'totalOpnameBerjalan'     => $totalOpnameBerjalan,
            'opnameBerjalan'          => $opnameBerjalan,
            'totalPenyesuaianMenunggu'=> $totalPenyesuaianMenunggu,
            'penyesuaianMenunggu'     => $penyesuaianMenunggu,
            'totalBatchAktif'         => $totalBatchAktif,
        ])->layout('layouts.portal', ['portal' => 'kepala-gudang', 'title' => 'Dashboard Kepala Gudang']);
    }
}
