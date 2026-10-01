<?php

namespace App\Livewire\Distribusi;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\StokRuangan;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class StokRuanganIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    #[Url(history: true)]
    public string $activeTab = 'bhp'; // 'bhp' atau 'aset'

    #[Url(history: true)]
    public string $filterKondisiAset = ''; // '', 'BAIK', 'RUSAK_RINGAN', 'RUSAK_BERAT'

    #[Url(history: true)]
    public string $filterStatusAset = ''; // '', 'DIGUNAKAN', 'TERSEDIA', 'DIPINJAM'

    #[Url(history: true)]
    public string $filterKondisi = ''; // '', 'TERSEDIA', 'HABIS'

    #[Url(history: true)]
    public ?int $filterUnitId = null;

    public bool $showPakaiModal = false;
    public ?int $selectedStokId = null;
    public ?StokRuangan $selectedStok = null;

    public int $jumlah_pakai = 1;
    public string $keterangan = '';

    public function updatedFilterKondisi(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUnitId(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedFilterKondisiAset(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatusAset(): void
    {
        $this->resetPage();
    }

    public function openCatatPemakaian(int $id): void
    {
        $stok = StokRuangan::with(['barang.satuan', 'unit'])->findOrFail($id);

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $stok->unit_id) {
            abort(403, 'Anda tidak memiliki wewenang mencatat pemakaian persediaan unit lain.');
        }

        if ($stok->jumlah <= 0) {
            $this->toast("Persediaan barang '{$stok->barang?->nama}' sudah habis di ruangan.", 'warning');
            return;
        }

        $this->selectedStokId = $stok->id;
        $this->selectedStok = $stok;
        $this->jumlah_pakai = 1;
        $this->keterangan = '';
        $this->showPakaiModal = true;
    }

    public function simpanPemakaian(): void
    {
        $this->validate([
            'jumlah_pakai' => 'required|integer|min:1',
            'keterangan'   => 'required|string|max:500',
        ], [
            'jumlah_pakai.required' => 'Kuantitas pemakaian wajib diisi.',
            'jumlah_pakai.integer'  => 'Kuantitas harus berupa angka bulat.',
            'jumlah_pakai.min'      => 'Kuantitas pemakaian minimal 1.',
            'keterangan.required'   => 'Keterangan atau keperluan pemakaian wajib diisi.',
        ]);

        if (! $this->selectedStokId) {
            return;
        }

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        try {
            DB::transaction(function () use ($user, $isGlobalAdmin) {
                $stok = StokRuangan::lockForUpdate()->with(['barang.satuan', 'unit'])->findOrFail($this->selectedStokId);

                if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $stok->unit_id) {
                    throw new \Exception('Anda tidak berwenang mencatat pemakaian persediaan ruangan lain.');
                }

                if ($this->jumlah_pakai > $stok->jumlah) {
                    throw new \Exception("Kuantitas pakai ({$this->jumlah_pakai}) melebihi sisa fisik di lemari ({$stok->jumlah}).");
                }

                $sisaBaru = max(0, $stok->jumlah - $this->jumlah_pakai);
                $stok->update(['jumlah' => $sisaBaru]);
            });

            $namaBarang = $this->selectedStok?->barang?->nama ?? 'Barang';
            $satuan = $this->selectedStok?->barang?->satuan?->simbol ?? 'unit';
            $this->toast("Pemakaian {$this->jumlah_pakai} {$satuan} '{$namaBarang}' berhasil dicatat.", 'success');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
            return;
        }

        $this->showPakaiModal = false;
        $this->selectedStokId = null;
        $this->selectedStok = null;
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('page_StokRuanganIndex') ||
            auth()->user()?->can('view_any_layanan_unit::stok::ruangan') ||
            auth()->user()?->can('view_layanan_unit::stok::ruangan') ||
            auth()->user()?->can('page_Dashboard') ||
            auth()->user()?->can('page_AdminLogistikDashboard'),
            403,
            'Anda tidak memiliki akses ke halaman persediaan lokal ruangan.'
        );

        $user = auth()->user();
        $isGlobalAdmin = $user && ($user->can('page_Dashboard') || $user->can('page_AdminLogistikDashboard'));

        // Target unit query
        $effectiveUnitId = $isGlobalAdmin ? $this->filterUnitId : $user?->unit_id;

        $baseQuery = StokRuangan::query()
            ->when($effectiveUnitId, fn ($q) => $q->where('unit_id', $effectiveUnitId))
            ->when(! $effectiveUnitId && ! $isGlobalAdmin, fn ($q) => $q->whereRaw('1 = 0'));

        // Metrik Ringkasan
        $countTotalItem = (clone $baseQuery)->count();
        $countTersedia = (clone $baseQuery)->where('jumlah', '>', 0)->count();
        $countHabis = (clone $baseQuery)->where('jumlah', '<=', 0)->count();

        // Data List
        $query = (clone $baseQuery)
            ->with(['barang.satuan', 'barang.kategori', 'unit'])
            ->when($this->filterKondisi === 'TERSEDIA', fn ($q) => $q->where('jumlah', '>', 0))
            ->when($this->filterKondisi === 'HABIS', fn ($q) => $q->where('jumlah', '<=', 0))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->whereHas('barang', function ($b) {
                        $b->where('nama', 'ilike', "%{$this->search}%")
                            ->orWhere('kode', 'ilike', "%{$this->search}%");
                    })
                    ->orWhereHas('unit', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"));
                });
            });
        $daftarStok = $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        // 2. Query Aset Fisik Ruangan
        $baseAsetQuery = Aset::query()
            ->when($effectiveUnitId, fn ($q) => $q->where('unit_id', $effectiveUnitId))
            ->when(! $effectiveUnitId && ! $isGlobalAdmin, fn ($q) => $q->whereRaw('1 = 0'));

        $countTotalAset = (clone $baseAsetQuery)->count();
        $countAsetBaik = (clone $baseAsetQuery)->where('kondisi', 'BAIK')->count();

        $asetQuery = (clone $baseAsetQuery)
            ->with(['barang.kategori', 'barang.satuan', 'unit'])
            ->when($this->filterKondisiAset, fn ($q) => $q->where('kondisi', $this->filterKondisiAset))
            ->when($this->filterStatusAset, fn ($q) => $q->where('status', $this->filterStatusAset))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode_aset', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        $daftarAset = $asetQuery
            ->orderBy($this->sortBy ?: 'kode_aset', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate($this->perPage);

        $daftarUnit = $isGlobalAdmin ? Unit::where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama', 'kode']) : collect();

        $pageTitle = ($user?->unit && ! $isGlobalAdmin)
            ? "Persediaan Lokal Ruangan ({$user->unit->nama})"
            : 'Persediaan Lokal Ruangan';

        return view('livewire.distribusi.stok-ruangan-index', [
            'activeTab'      => $this->activeTab,
            'daftarStok'     => $daftarStok,
            'daftarAset'     => $daftarAset,
            'daftarUnit'     => $daftarUnit,
            'isGlobalAdmin'  => $isGlobalAdmin,
            'userUnitId'     => $user?->unit_id,
            'countTotalItem' => $countTotalItem,
            'countTersedia'  => $countTersedia,
            'countHabis'     => $countHabis,
            'countTotalAset' => $countTotalAset,
            'countAsetBaik'  => $countAsetBaik,
            'sortBy'         => $this->sortBy,
            'sortDirection'  => $this->sortDirection,
            'selectedStok'   => $this->selectedStok,
            'pageTitle'      => $pageTitle,
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => $pageTitle,
        ]);
    }
}
