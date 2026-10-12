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
        $user = auth()->user();
        abort_unless(
            $user?->can('view_any_pengadaan::realisasi::anggaran') || $user?->can('view_pengadaan::realisasi::anggaran'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang melihat Pagu & Realisasi Anggaran.'
        );
    }

    /**
     * Baca lintas unit hanya untuk role global operasional serta pembaca global
     * (auditor, manajemen, bagian pengadaan) yang sudah lolos permission view.
     */
    private function hasGlobalRead(): bool
    {
        return (bool) auth()->user()?->hasRole(['super_admin', 'admin_logistik', 'auditor', 'manajemen', 'pengadaan']);
    }

    private function canReadUnit(int $unitId): bool
    {
        $userUnitId = auth()->user()?->unit_id;

        return $this->hasGlobalRead() || ($userUnitId && (int) $userUnitId === $unitId);
    }

    public function openDetail(int $id): void
    {
        abort_unless($this->canReadUnit($id), 403, 'Akses ditolak: Anda hanya dapat melihat realisasi anggaran unit Anda sendiri.');

        $this->selectedUnitId = $id;
        $this->selectedAnggaranId = $id;
        $this->showDetailModal = true;
    }

    public function getSelectedUnitProperty(): ?Unit
    {
        $id = $this->selectedUnitId ?? $this->selectedAnggaranId;
        if (! $id || ! $this->canReadUnit((int) $id)) {
            return null;
        }

        return Unit::find($id);
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
        $unitQuery = Unit::query()
            ->when(! $this->hasGlobalRead(), fn ($q) => $q->where('id', auth()->user()?->unit_id));

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
            ->when(! $this->hasGlobalRead(), fn ($q) => $q->whereHas('permintaanMaintenance', fn ($m) => $m->where('unit_id', auth()->user()?->unit_id)))
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
