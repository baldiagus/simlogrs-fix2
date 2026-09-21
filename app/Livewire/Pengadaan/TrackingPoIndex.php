<?php

namespace App\Livewire\Pengadaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\PesananPembelian;
use Livewire\Attributes\Url;
use Livewire\Component;

class TrackingPoIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showDetailModal = false;
    public ?int $selectedPoId = null;
    public ?PesananPembelian $selectedPo = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    public function showDetail(int $id): void
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::tracking::po'), 403);

        $this->selectedPo = PesananPembelian::with(['pemasok', 'detail.barang', 'permintaanPengadaan', 'pembuat', 'penerimaanBarang'])
            ->findOrFail($id);
        $this->selectedPoId = $id;
        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_pengadaan::tracking::po'), 403);

        $items = PesananPembelian::with(['pemasok'])
            ->whereIn('status', ['DITERBITKAN', 'DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhereHas('pemasok', fn ($p) => $p->where('nama', 'ilike', "%{$this->search}%"));
                });
            })
            ->orderBy($this->sortBy === 'id' ? 'tanggal_po' : $this->sortBy, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.pengadaan.tracking-po-index', [
            'items' => $items,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Tracking PO Vendor']);
    }
}
