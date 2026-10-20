<?php

namespace App\Livewire\Dashboard;

use App\Enums\Distribusi\StatusPengirimanBarang;
use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Enums\Pengadaan\StatusPesananPembelian;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Pengadaan\PesananPembelian;
use App\Models\Persediaan\StokBatch;
use Livewire\Component;

class GudangDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_GudangDashboard'), 403);

        $gudangId = auth()->user()?->gudang_id;

        // 1. Antrean Picking SPPB (Permintaan sudah disetujui Meja 2)
        $antreanPicking = PermintaanDistribusi::whereIn('status', StatusPermintaanDistribusi::siapPicking())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->with(['unit', 'pengaju', 'detail.barang'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAntreanPicking = PermintaanDistribusi::whereIn('status', StatusPermintaanDistribusi::siapPicking())
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        // 2. Inbound PO Vendor Menunggu Diterima
        $inboundPo = PesananPembelian::whereIn('status', StatusPesananPembelian::bisaDiterima())
            ->with('pemasok')
            ->latest('id')
            ->take(5)
            ->get();
        $totalInboundPo = PesananPembelian::whereIn('status', StatusPesananPembelian::bisaDiterima())->count();

        // 3. SPPB Sedang Dalam Pengiriman
        $totalSppbDikirim = PengirimanBarang::where('status', StatusPengirimanBarang::Dikirim->value)
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        // 4. Total Batch Fisik Dikelola
        $totalBatchAktif = StokBatch::where('status', 'AKTIF')
            ->when($gudangId, fn($q) => $q->where('gudang_id', $gudangId))
            ->count();

        return view('livewire.dashboard.gudang-dashboard', [
            'totalAntreanPicking' => $totalAntreanPicking,
            'antreanPicking'      => $antreanPicking,
            'totalInboundPo'      => $totalInboundPo,
            'inboundPo'           => $inboundPo,
            'totalSppbDikirim'    => $totalSppbDikirim,
            'totalBatchAktif'     => $totalBatchAktif,
        ])->layout('layouts.portal', ['portal' => 'petugas-gudang', 'title' => 'Dashboard Petugas Gudang']);
    }
}
