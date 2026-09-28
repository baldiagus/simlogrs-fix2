<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Pemasok;
use App\Models\PesananPembelian;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MasterRekananIndex extends Component
{
    use HasPortalLayout, HasToast, HasTableFilters, WithPagination;

    #[Url(history: true)]
    public string $filterKategori = ''; // 'Semua', 'Aktif', 'Nonaktif'

    public ?int $selectedPemasokId = null;
    public bool $showDetailModal = false;

    public function boot(): void
    {
        abort_unless(
            auth()->user()?->can('page_MasterRekananIndex') ||
            auth()->user()?->can('view_any_pengadaan::master::rekanan') ||
            auth()->user()?->can('page_ManajemenDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang melihat Evaluasi Rekanan Vendor.'
        );
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('page_MasterRekananIndex') ||
            auth()->user()?->can('view_any_pengadaan::master::rekanan') ||
            auth()->user()?->can('page_ManajemenDashboard'),
            403,
            'Akses ditolak: Anda tidak memiliki wewenang melihat Evaluasi Rekanan Vendor.'
        );
    }

    public function openDetail(int $id): void
    {
        $this->selectedPemasokId = $id;
        $this->showDetailModal = true;
    }

    public function getSelectedPemasokProperty(): ?Pemasok
    {
        return $this->selectedPemasokId
            ? Pemasok::with(['pesananPembelian' => function ($q) {
                $q->latest('id')->take(10);
            }])->find($this->selectedPemasokId)
            : null;
    }

    public function render()
    {
        $query = Pemasok::withCount('pesananPembelian')
            ->withSum('pesananPembelian', 'total_biaya');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('nama', 'ilike', "%{$this->search}%")
                  ->orWhere('kode', 'ilike', "%{$this->search}%")
                  ->orWhere('kota', 'ilike', "%{$this->search}%");
            });
        }

        if (! empty($this->filterKategori)) {
            $query->where('status', $this->filterKategori);
        }

        $items = $query->orderBy('nama')->paginate(10);

        $totalRekanan = Pemasok::count();
        $rekananAktif = Pemasok::where('status', 'AKTIF')->count();
        $totalBelanjaVendor = (float) PesananPembelian::whereIn('status', ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN', 'DITERIMA_LENGKAP'])->sum('total_biaya');

        return view('livewire.pengadaan.master-rekanan-index', [
            'items' => $items,
            'totalRekanan' => $totalRekanan,
            'rekananAktif' => $rekananAktif,
            'totalBelanjaVendor' => $totalBelanjaVendor,
            'selectedPemasok' => $this->selectedPemasok,
        ])->layout('layouts.portal', [
            'title' => 'Evaluasi Kinerja Rekanan & Pemasok Vendor',
            'portal' => $this->getPortalName(),
        ]);
    }
}
