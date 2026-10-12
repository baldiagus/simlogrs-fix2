<?php

namespace App\Livewire\Dashboard;

use App\Models\Aset;
use App\Models\PermintaanMaintenance;
use App\Models\StokBatch;
use App\Models\TemuanAudit;
use App\Services\Anggaran\AnggaranRealtimeService;
use Livewire\Component;

class ManajemenDashboard extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('page_ManajemenDashboard') || auth()->user()?->can('page_' . class_basename(static::class)), 403);

        // 1. Valuasi Persediaan Fisik Gudang
        $valuasiPersediaan = (float) StokBatch::where('status', 'AKTIF')
            ->selectRaw('COALESCE(SUM(jumlah * harga_satuan), 0) as total')
            ->value('total');

        // 2. Realisasi Pengeluaran Logistik (Amprah) & Konsumsi Sparepart IPSRS
        $service = app(AnggaranRealtimeService::class);
        $ringkasanAnggaran = $service->getRingkasanEksekutif('2026');
        $totalPagu = $ringkasanAnggaran['total_pagu'];
        $totalRealisasi = $ringkasanAnggaran['total_realisasi_global'];
        $serapanPersen = $ringkasanAnggaran['serapan_global_persen'];
        $totalDistribusi = $ringkasanAnggaran['total_realisasi_distribusi'];
        $totalSparepart = $ringkasanAnggaran['total_realisasi_sparepart'];
        // 3. Indeks Kelaikan Sarana Prasarana IPSRS
        $totalAset = Aset::count();
        $asetBaik = Aset::where('kondisi', 'BAIK')->count();
        $indeksKelaikan = $totalAset > 0 ? round(($asetBaik / $totalAset) * 100, 1) : 100;

        // 4. Tata Kelola & Closing Rate Audit CAPA
        $totalTemuan = TemuanAudit::count();
        $closedTemuan = TemuanAudit::where('status_capa', 'Closed')->count();
        $closingRate = $totalTemuan > 0 ? round(($closedTemuan / $totalTemuan) * 100, 1) : 100;

        // 5. Early Warning: Batch Kedaluwarsa Kritis (<= 90 hari)
        $batchKritis = StokBatch::with(['barang', 'gudang'])
            ->where('status', 'AKTIF')
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', now()->addDays(90)->toDateString())
            ->orderBy('tanggal_kedaluwarsa')
            ->take(5)
            ->get();

        // 6. Early Warning: Tiket IPSRS Prioritas CITO / Tinggi yang Belum Selesai
        $spkMendesak = PermintaanMaintenance::with(['aset', 'unit'])
            ->whereIn('prioritas', ['CITO', 'TINGGI'])
            ->whereNotIn('status', ['SELESAI', 'DIBATALKAN'])
            ->latest('id')
            ->take(5)
            ->get();

        return view('livewire.dashboard.manajemen-dashboard', [
            'valuasiPersediaan' => $valuasiPersediaan,
            'totalPagu'         => $totalPagu,
            'totalRealisasi'    => $totalRealisasi,
            'serapanPersen'     => $serapanPersen,
            'totalDistribusi'   => $totalDistribusi,
            'totalSparepart'    => $totalSparepart,
            'totalAset'         => $totalAset,
            'asetBaik'          => $asetBaik,
            'indeksKelaikan'    => $indeksKelaikan,
            'totalTemuan'       => $totalTemuan,
            'closedTemuan'      => $closedTemuan,
            'closingRate'       => $closingRate,
            'batchKritis'       => $batchKritis,
            'spkMendesak'       => $spkMendesak,
        ])->layout('layouts.portal', ['portal' => 'manajemen', 'title' => 'Dashboard Eksekutif Direksi']);
    }
}
