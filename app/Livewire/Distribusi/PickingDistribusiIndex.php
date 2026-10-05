<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Gudang;
use App\Models\PengirimanBarang;
use App\Models\PermintaanDistribusi;
use App\Services\Distribusi\DistribusiService;
use Livewire\Attributes\Url;
use Livewire\Component;

class PickingDistribusiIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $activeTab = 'siap-picking'; // 'siap-picking' | 'riwayat-sppb'

    #[Url(history: true)]
    public string $filterUrgensi = '';

    public bool $showDetailModal = false;
    public ?PengirimanBarang $selectedSppb = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrgensi(): void
    {
        $this->resetPage();
    }

    public function confirmPicking(int $permintaanId): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('create_gudang_stok::picking::distribusi') ||
                $user->can('update_gudang_stok::picking::distribusi') ||
                $user->can('page_PickingDistribusiIndex') ||
                $user->can('page_GudangDashboard') ||
                $user->can('page_Dashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk melakukan picking FEFO.'
        );

        $permintaan = PermintaanDistribusi::with(['unit', 'detail'])->find($permintaanId);
        if (! $permintaan) {
            $this->toast('Data permintaan amprah tidak ditemukan.', 'danger');
            return;
        }

        if (! in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN'])) {
            $this->toast("Permintaan {$permintaan->kode} belum disetujui gudang atau sudah selesai diproses.", 'warning');
            return;
        }

        $unitNama = $permintaan->unit?->nama ?? 'Unit';
        $itemCount = $permintaan->detail->count();

        $this->askConfirmation(
            target: $permintaanId,
            action: 'executePicking',
            title: 'Konfirmasi Picking FEFO',
            description: "Eksekusi alokasi stok FEFO untuk unit {$unitNama} ({$itemCount} item barang)? Sistem akan mengalokasikan batch sesuai kedaluwarsa terdekat, memotong saldo stok gudang secara otomatis, dan menerbitkan SPPB.",
            buttonText: 'Eksekusi Picking FEFO',
            variant: 'primary'
        );
    }

    public function executePicking(int $permintaanId): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('create_gudang_stok::picking::distribusi') ||
                $user->can('update_gudang_stok::picking::distribusi') ||
                $user->can('page_PickingDistribusiIndex') ||
                $user->can('page_GudangDashboard') ||
                $user->can('page_Dashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk mengeksekusi picking FEFO.'
        );

        $permintaan = PermintaanDistribusi::with(['detail.barang', 'unit'])->find($permintaanId);
        if (! $permintaan) {
            $this->toast('Data permintaan amprah tidak ditemukan.', 'danger');
            return;
        }

        if (! in_array($permintaan->status, ['DISETUJUI_GUDANG', 'DISETUJUI_KARU'])) {
            $this->toast("Status permintaan {$permintaan->kode} ({$permintaan->status}) tidak valid untuk picking.", 'warning');
            return;
        }

        // Pastikan gudang_id terisi jika belum ada
        if (! $permintaan->gudang_id) {
            $gudangId = $user->gudang_id ?: Gudang::where('status', 'AKTIF')->first()?->id ?: 1;
            $permintaan->update(['gudang_id' => $gudangId]);
            $permintaan->refresh();
        }

        try {
            $sppb = app(DistribusiService::class)->eksekusiPickingFefo($permintaan, $user);

            $this->toast("SPPB {$sppb->kode} berhasil diterbitkan dengan alokasi FEFO!", 'success');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger', 'Gagal Picking FEFO');
        }
    }

    public function showSppbDetail(int $id): void
    {
        $this->checkAccess();

        $this->selectedSppb = PengirimanBarang::with([
            'permintaanDistribusi',
            'unit',
            'gudang',
            'pengirim',
            'detail.barang.satuan',
            'detail.stokBatch.lokasiGudang',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedSppb = null;
    }

    protected function checkAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (
                $user->can('page_PickingDistribusiIndex') ||
                $user->can('view_any_gudang_stok::picking::distribusi') ||
                $user->can('view_gudang_stok::picking::distribusi') ||
                $user->can('create_gudang_stok::picking::distribusi') ||
                $user->can('update_gudang_stok::picking::distribusi') ||
                $user->can('page_Dashboard') ||
                $user->can('page_GudangDashboard') ||
                $user->can('page_KepalaGudangDashboard') ||
                $user->can('page_AdminLogistikDashboard')
            ),
            403,
            'Anda tidak memiliki wewenang untuk mengakses halaman Picking FEFO & SPPB.'
        );
    }

    public function render()
    {
        $this->checkAccess();

        $user = auth()->user();
        $isGlobalAdmin = $user && (
            $user->can('page_Dashboard') ||
            $user->can('page_AdminLogistikDashboard') ||
            $user->can('page_KepalaGudangDashboard')
        );

        // Badge count untuk antrean Siap Picking
        $siapPickingCount = PermintaanDistribusi::whereIn('status', ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN'])
            ->when($user?->gudang_id && ! $isGlobalAdmin, function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('gudang_id', $user->gudang_id)->orWhereNull('gudang_id');
                });
            })
            ->count();

        if ($this->activeTab === 'siap-picking') {
            $query = PermintaanDistribusi::with(['unit', 'gudang', 'pengaju', 'detail.barang.satuan'])
                ->withCount('detail')
                ->whereIn('status', ['DISETUJUI_GUDANG', 'DIKIRIM_SEBAGIAN'])
                ->when($user?->gudang_id && ! $isGlobalAdmin, function ($q) use ($user) {
                    $q->where(function ($sub) use ($user) {
                        $sub->where('gudang_id', $user->gudang_id)->orWhereNull('gudang_id');
                    });
                })
                ->when($this->filterUrgensi, fn ($q) => $q->where('urgensi', $this->filterUrgensi))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                    });
                });

            if ($this->sortBy === 'id' || ! $this->sortBy) {
                $query->orderByRaw("CASE WHEN urgensi = 'CITO' THEN 0 ELSE 1 END")
                    ->orderBy('tanggal_permintaan', 'asc')
                    ->orderBy('id', 'asc');
            } else {
                $sortCol = in_array($this->sortBy, ['kode', 'tanggal_permintaan', 'urgensi', 'status', 'id']) ? $this->sortBy : 'id';
                $query->orderBy($sortCol, $this->sortDirection === 'asc' ? 'asc' : 'desc');
            }

            $items = $query->paginate($this->perPage);
        } else {
            $query = PengirimanBarang::with([
                'permintaanDistribusi',
                'unit',
                'gudang',
                'pengirim',
                'detail.barang.satuan',
                'detail.stokBatch.lokasiGudang',
            ])
                ->when($user?->gudang_id && ! $isGlobalAdmin, fn ($q) => $q->where('gudang_id', $user->gudang_id))
                ->when($this->search, function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kode', 'ilike', "%{$this->search}%")
                            ->orWhereHas('permintaanDistribusi', fn ($p) => $p->where('kode', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"))
                            ->orWhereHas('detail.stokBatch', fn ($sb) => $sb->where('nomor_batch', 'ilike', "%{$this->search}%"));
                    });
                });

            $sortCol = in_array($this->sortBy, ['kode', 'tanggal_kirim', 'status', 'id']) ? $this->sortBy : 'id';
            $query->orderBy($sortCol, $this->sortDirection === 'asc' ? 'asc' : 'desc');

            $items = $query->paginate($this->perPage);
        }

        return view('livewire.distribusi.picking-distribusi-index', [
            'items'            => $items,
            'siapPickingCount' => $siapPickingCount,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => 'Picking FEFO & Pengeluaran Barang (SPPB)',
        ]);
    }
}
