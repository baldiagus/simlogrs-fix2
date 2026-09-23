<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\AnggaranPengadaan;
use App\Models\PengirimanBarang;
use App\Models\SukuCadangMaintenance;
use App\Services\Anggaran\AnggaranRealtimeService;
use Livewire\Attributes\Url;
use Livewire\Component;

class RealisasiAnggaranIndex extends Component
{
    use HasPortalLayout, HasToast, HasTableFilters;

    #[Url(history: true)]
    public string $filterTahun = '2026';

    #[Url(history: true)]
    public string $activeTab = 'unit'; // 'unit' | 'sparepart'

    public ?int $selectedAnggaranId = null;
    public bool $showDetailModal = false;

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('page_RealisasiAnggaranIndex') ||
            auth()->user()?->can('view_any_pengadaan::realisasi::anggaran') ||
            auth()->user()?->can('page_ManajemenDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang melihat Pagu & Realisasi Anggaran.'
        );
    }

    public function openDetail(int $id): void
    {
        $this->selectedAnggaranId = $id;
        $this->showDetailModal = true;
    }

    public function getSelectedAnggaranProperty(): ?AnggaranPengadaan
    {
        return $this->selectedAnggaranId
            ? AnggaranPengadaan::with('unit')->find($this->selectedAnggaranId)
            : null;
    }

    public function render()
    {
        $service = app(AnggaranRealtimeService::class);
        $service->syncRealisasiAnggaran($this->filterTahun);
        $ringkasan = $service->getRingkasanEksekutif($this->filterTahun);

        // 1. Data Tab Unit Ruangan
        $unitQuery = AnggaranPengadaan::with('unit')
            ->where('tahun', $this->filterTahun)
            ->where('tipe', 'UNIT');

        if (! empty($this->search)) {
            $unitQuery->where(function ($q) {
                $q->where('pos_anggaran', 'ilike', "%{$this->search}%")
                  ->orWhere('kode', 'ilike', "%{$this->search}%")
                  ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
            });
        }

        $unitAnggarans = $unitQuery->orderBy('kode')->get()->map(function ($anggaran) use ($service) {
            $anggaran->beban_distribusi = $anggaran->unit_id ? $service->hitungBebanDistribusiUnit($anggaran->unit_id, $this->filterTahun) : 0;
            $anggaran->beban_sparepart = $anggaran->unit_id ? $service->hitungBebanSparepartUnit($anggaran->unit_id, $this->filterTahun) : 0;
            return $anggaran;
        });

        // 2. Data Tab Pos Sparepart IPSRS
        $sparepartAnggaran = AnggaranPengadaan::with('unit')
            ->where('tahun', $this->filterTahun)
            ->where('tipe', 'SPAREPART_IPSRS')
            ->first();

        $topAset = $service->getTopAsetBiayaPemeliharaan($this->filterTahun, 5);

        $riwayatSparepartTerbaru = SukuCadangMaintenance::with(['barang', 'stokBatch', 'permintaanMaintenance.aset', 'permintaanMaintenance.unit'])
            ->whereYear('dibuat_pada', $this->filterTahun)
            ->latest('id')
            ->take(10)
            ->get();

        // 3. Drilldown Data untuk Unit yang Dipilih di Modal
        $riwayatSppbUnit = collect();
        $riwayatServisUnit = collect();

        if ($this->selectedAnggaran && $this->selectedAnggaran->unit_id) {
            $uId = $this->selectedAnggaran->unit_id;

            $riwayatSppbUnit = PengirimanBarang::with(['gudang', 'detail.barang', 'detail.stokBatch'])
                ->where('unit_id', $uId)
                ->whereIn('status', ['DIKIRIM', 'DITERIMA'])
                ->whereYear('tanggal_kirim', $this->filterTahun)
                ->latest('tanggal_kirim')
                ->take(10)
                ->get();

            $riwayatServisUnit = SukuCadangMaintenance::with(['barang', 'permintaanMaintenance.aset'])
                ->whereHas('permintaanMaintenance', fn ($q) => $q->where('unit_id', $uId))
                ->whereYear('dibuat_pada', $this->filterTahun)
                ->latest('id')
                ->take(10)
                ->get();
        }

        return view('livewire.pengadaan.realisasi-anggaran-index', [
            'ringkasan'               => $ringkasan,
            'unitAnggarans'           => $unitAnggarans,
            'sparepartAnggaran'       => $sparepartAnggaran,
            'topAset'                 => $topAset,
            'riwayatSparepartTerbaru' => $riwayatSparepartTerbaru,
            'riwayatSppbUnit'         => $riwayatSppbUnit,
            'riwayatServisUnit'       => $riwayatServisUnit,
            'selectedAnggaran'        => $this->selectedAnggaran,
        ])->layout('layouts.portal', [
            'title' => 'Pagu & Realisasi Anggaran Pengeluaran Logistik & IPSRS',
            'portal' => $this->getPortalName(),
        ]);
    }
}
