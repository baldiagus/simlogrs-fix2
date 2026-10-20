<?php

namespace App\Livewire\Dashboard;

use App\Enums\Pengadaan\StatusPermintaanPengadaan;
use App\Enums\Pengadaan\StatusPesananPembelian;
use App\Models\Master\Pemasok;
use App\Models\Pengadaan\PermintaanPengadaan;
use App\Models\Pengadaan\PesananPembelian;
use App\Services\Anggaran\AnggaranRealtimeService;
use Livewire\Component;

class PengadaanDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_PengadaanDashboard'), 403);

        // 1. Antrean Permintaan Pengadaan (PR) Siap Diterbitkan PO
        $antreanPr = PermintaanPengadaan::whereIn('status', StatusPermintaanPengadaan::siapPo())
            ->with(['unit', 'gudang', 'pengaju', 'detail.barang'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalPrSiapPo = PermintaanPengadaan::whereIn('status', StatusPermintaanPengadaan::siapPo())->count();

        // 2. Pesanan Pembelian (PO) Aktif Berjalan
        $poAktif = PesananPembelian::whereIn('status', StatusPesananPembelian::aktif())
            ->with(['pemasok', 'detail'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalPoAktif = PesananPembelian::whereIn('status', StatusPesananPembelian::aktif())->count();

        // 3. Ringkasan Pagu & Realisasi Belanja Pengadaan
        $anggaranService = app(AnggaranRealtimeService::class);
        $ringkasan = $anggaranService->getRingkasanEksekutif('2026');
        $totalPagu = $ringkasan['total_pagu'];
        $totalRealisasi = $ringkasan['total_realisasi_global'];
        $serapanPersen = $ringkasan['serapan_global_persen'];

        // 4. Total Rekanan Vendor Terdaftar
        $totalVendor = Pemasok::count();

        return view('livewire.dashboard.pengadaan-dashboard', [
            'totalPrSiapPo'  => $totalPrSiapPo,
            'antreanPr'      => $antreanPr,
            'totalPoAktif'   => $totalPoAktif,
            'poAktif'        => $poAktif,
            'totalPagu'      => $totalPagu,
            'totalRealisasi' => $totalRealisasi,
            'serapanPersen'  => $serapanPersen,
            'totalVendor'    => $totalVendor,
        ])->layout('layouts.portal', ['portal' => 'pengadaan', 'title' => 'Dashboard Bagian Pengadaan']);
    }
}
