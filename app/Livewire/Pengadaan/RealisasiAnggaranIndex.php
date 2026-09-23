<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\AnggaranPengadaan;
use App\Models\PesananPembelian;
use Livewire\Attributes\Url;
use Livewire\Component;

class RealisasiAnggaranIndex extends Component
{
    use HasPortalLayout, HasToast, HasTableFilters;

    #[Url(history: true)]
    public string $filterTahun = '2026';

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
            ? AnggaranPengadaan::find($this->selectedAnggaranId)
            : null;
    }

    public function render()
    {
        $query = AnggaranPengadaan::where('tahun', $this->filterTahun);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('pos_anggaran', 'ilike', "%{$this->search}%")
                  ->orWhere('kode', 'ilike', "%{$this->search}%");
            });
        }

        $items = $query->orderBy('kode')->get();

        $totalPagu = (float) AnggaranPengadaan::where('tahun', $this->filterTahun)->sum('pagu');
        $totalRealisasi = (float) AnggaranPengadaan::where('tahun', $this->filterTahun)->sum('realisasi');
        $totalSisa = max(0, $totalPagu - $totalRealisasi);
        $serapanPersen = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 1) : 0;

        // Daftar PO yang sudah disetujui untuk konteks penelusuran
        $poList = PesananPembelian::with(['pemasok', 'pembuat'])
            ->whereIn('status', ['DISETUJUI', 'SELESAI', 'DITERIMA'])
            ->latest('id')
            ->take(10)
            ->get();
        return view('livewire.pengadaan.realisasi-anggaran-index', [
            'items' => $items,
            'totalPagu' => $totalPagu,
            'totalRealisasi' => $totalRealisasi,
            'totalSisa' => $totalSisa,
            'serapanPersen' => $serapanPersen,
            'poList' => $poList,
            'selectedAnggaran' => $this->selectedAnggaran,
        ])->layout('layouts.portal', [
            'title' => 'Pagu & Realisasi Anggaran Belanja Logistik',
            'portal' => $this->getPortalName(),
        ]);
    }
}
