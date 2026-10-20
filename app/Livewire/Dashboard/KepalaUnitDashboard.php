<?php

namespace App\Livewire\Dashboard;

use App\Enums\Distribusi\StatusPermintaanDistribusi;
use App\Enums\Ipsrs\StatusMaintenance;
use App\Models\Aset\PermintaanPeminjaman;
use App\Models\Distribusi\PermintaanDistribusi;
use App\Models\Distribusi\StokRuangan;
use App\Models\Ipsrs\PermintaanMaintenance;
use Livewire\Component;

class KepalaUnitDashboard extends Component
{
    /**
     * Render tampilan blade komponen Livewire.
     */
    public function render()
    {
        abort_unless(auth()->user()?->can('page_KepalaUnitDashboard'), 403);

        $unitId = auth()->user()?->unit_id;

        // 1. Antrean Approval Amprah Karu (Meja 1)
        $antreanAmprah = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->with(['pengaju', 'gudang', 'detail.barang'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAntreanAmprah = PermintaanDistribusi::where('status', StatusPermintaanDistribusi::Diajukan->value)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->count();

        // 2. Permohonan Pinjam Aset Menunggu Izin Karu Penyedia
        $antreanPinjam = PermintaanPeminjaman::where('status', 'DIAJUKAN')
            ->when($unitId, fn($q) => $q->where('unit_penyedia_id', $unitId))
            ->with(['unitPeminjam', 'pengaju'])
            ->latest('id')
            ->take(5)
            ->get();
        $totalAntreanPinjam = PermintaanPeminjaman::where('status', 'DIAJUKAN')
            ->when($unitId, fn($q) => $q->where('unit_penyedia_id', $unitId))
            ->count();

        // 3. Tiket Lapor Servis / Kerusakan Aktif di Ruangan
        $totalServisAktif = PermintaanMaintenance::whereIn('status', StatusMaintenance::aktif())
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->count();

        // 4. Komoditas Stok Ruangan Terdaftar
        $totalStokRuangan = StokRuangan::when($unitId, fn($q) => $q->where('unit_id', $unitId))->count();

        return view('livewire.dashboard.kepala-unit-dashboard', [
            'totalAntreanAmprah' => $totalAntreanAmprah,
            'antreanAmprah'      => $antreanAmprah,
            'totalAntreanPinjam' => $totalAntreanPinjam,
            'antreanPinjam'      => $antreanPinjam,
            'totalServisAktif'   => $totalServisAktif,
            'totalStokRuangan'   => $totalStokRuangan,
        ])->layout('layouts.portal', ['portal' => 'kepala-unit', 'title' => 'Dashboard Kepala Unit']);
    }
}
