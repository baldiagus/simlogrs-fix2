<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\AnggaranPengadaan;
use App\Models\PengirimanBarang;
use App\Models\SukuCadangMaintenance;
use App\Models\Unit;
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

    public ?int $selectedUnitId = null;
    public ?int $selectedAnggaranId = null;
    public bool $showDetailModal = false;

    public function boot(): void
    {
        abort_unless(
            auth()->user()?->can('page_RealisasiAnggaranIndex') ||
            auth()->user()?->can('view_any_pengadaan::realisasi::anggaran') ||
            auth()->user()?->can('page_ManajemenDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang melihat Pagu & Realisasi Anggaran.'
        );
    }

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
        $this->selectedUnitId = $id;
        $this->selectedAnggaranId = $id;
        $this->showDetailModal = true;
    }

    public function getSelectedUnitProperty(): ?Unit
    {
        $id = $this->selectedUnitId ?? $this->selectedAnggaranId;
        return $id ? Unit::find($id) : null;
    }

    public function getSelectedAnggaranProperty(): ?Unit
    {
        return $this->selectedUnit;
    }

    public function render()
    {
        $service = app(AnggaranRealtimeService::class);
        $service->syncRealisasiAnggaran($this->filterTahun);
        $ringkasan = $service->getRingkasanEksekutif($this->filterTahun);

        // 1. Data Tab Unit Ruangan
        $unitQuery = Unit::query();

        if (! empty($this->search)) {
            $unitQuery->where(function ($q) {
                $q->where('nama', 'ilike', "%{$this->search}%")
                  ->orWhere('kode', 'ilike', "%{$this->search}%");
            });
        }

        $unitAnggarans = $unitQuery->orderBy('nama')->get()->map(function ($unit) use ($service) {
            $unit->beban_distribusi = $service->hitungBebanDistribusiUnit($unit->id, $this->filterTahun);
            $unit->beban_sparepart = $service->hitungBebanSparepartUnit($unit->id, $this->filterTahun);
            $unit->realisasi = $unit->beban_distribusi + $unit->beban_sparepart;
            return $unit;
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
        $selectedBebanDistribusi = 0;
        $selectedBebanSparepart = 0;

        if ($this->selectedUnit) {
            $uId = $this->selectedUnit->id;
            $selectedBebanDistribusi = $service->hitungBebanDistribusiUnit($uId, $this->filterTahun);
            $selectedBebanSparepart = $service->hitungBebanSparepartUnit($uId, $this->filterTahun);

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
            'selectedUnit'            => $this->selectedUnit,
            'selectedAnggaran'        => $this->selectedUnit,
            'selectedBebanDistribusi' => $selectedBebanDistribusi,
            'selectedBebanSparepart'  => $selectedBebanSparepart,
        ])->layout('layouts.portal', [
            'title' => 'Screening Pengeluaran Logistik Ruangan & IPSRS',
            'portal' => $this->getPortalName(),
        ]);
    }
}
