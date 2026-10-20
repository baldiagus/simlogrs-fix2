<?php

namespace App\Livewire\Dashboard;

use App\Enums\Distribusi\StatusPengirimanBarang;
use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Enums\Ipsrs\StatusMaintenance;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Distribusi\PengirimanBarang;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Ipsrs\PermintaanMaintenance;
use Livewire\Component;

class UnitDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_UnitDashboard'), 403);

        $unitId = auth()->user()?->unit_id;

        // 1. Pengiriman SPPB Menunggu Konfirmasi Terima (RCV)
        $antreanRcv = PengirimanBarang::where('status', StatusPengirimanBarang::Dikirim->value)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->with(['gudang', 'permintaanDistribusi'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAntreanRcv = PengirimanBarang::where('status', StatusPengirimanBarang::Dikirim->value)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->count();

        // 2. Permintaan Amprah Masih Berjalan
        $amprahBerjalan = PermintaanDistribusi::whereIn('status', [
                StatusPermintaanDistribusi::Diajukan->value,
                StatusPermintaanDistribusi::DisetujuiKaru->value,
                StatusPermintaanDistribusi::DisetujuiGudang->value,
                StatusPermintaanDistribusi::SedangDikirim->value,
            ])
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->with(['gudang', 'detail.barang'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAmprahBerjalan = PermintaanDistribusi::whereIn('status', [
                StatusPermintaanDistribusi::Diajukan->value,
                StatusPermintaanDistribusi::DisetujuiKaru->value,
                StatusPermintaanDistribusi::DisetujuiGudang->value,
                StatusPermintaanDistribusi::SedangDikirim->value,
            ])
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->count();

        // 3. Aset Ruangan Sedang Dipinjamkan Keluar
        $totalAsetDipinjam = PermintaanPeminjaman::where('status', 'DISETUJUI')
            ->when($unitId, fn($q) => $q->where('unit_penyedia_id', $unitId))
            ->count();

        // 4. Laporan Kerusakan Sarana Aktif
        $totalLaporServis = PermintaanMaintenance::whereIn('status', StatusMaintenance::aktif())
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->count();

        return view('livewire.dashboard.unit-dashboard', [
            'totalAntreanRcv'     => $totalAntreanRcv,
            'antreanRcv'          => $antreanRcv,
            'totalAmprahBerjalan' => $totalAmprahBerjalan,
            'amprahBerjalan'      => $amprahBerjalan,
            'totalAsetDipinjam'   => $totalAsetDipinjam,
            'totalLaporServis'    => $totalLaporServis,
        ])->layout('layouts.portal', ['portal' => 'petugas-unit', 'title' => 'Dashboard Petugas Ruangan']);
    }
}
