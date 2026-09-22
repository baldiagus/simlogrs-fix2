<?php

namespace App\Livewire\Persediaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PergerakanStok;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;

class BukuMutasiIndex extends Component
{
    use HasTableFilters, HasPortalLayout;

    #[Url(history: true)]
    public string $filterJenis = '';

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    #[Url(history: true)]
    public ?int $filterBarangId = null;

    #[Url(history: true)]
    public ?string $tanggalMulai = null;

    #[Url(history: true)]
    public ?string $tanggalSelesai = null;

    public bool $showDetailModal = false;
    public ?PergerakanStok $selectedMutasi = null;

    public function showDetail(int $id): void
    {
        $this->selectedMutasi = PergerakanStok::with([
            'barang.kategori',
            'barang.satuan',
            'stokBatch',
            'gudang',
            'lokasiGudang',
            'pembuatRecord',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function resetFilters(): void
    {
        $this->reset(['filterJenis', 'filterGudangId', 'filterBarangId', 'tanggalMulai', 'tanggalSelesai', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::buku::mutasi') ||
            auth()->user()?->can('view_gudang_stok::buku::mutasi'),
            403
        );

        $query = PergerakanStok::with(['barang.satuan', 'stokBatch', 'gudang', 'lokasiGudang', 'pembuatRecord'])
            ->when($this->filterJenis, fn ($q) => $q->where('jenis', $this->filterJenis))
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->filterBarangId, fn ($q) => $q->where('barang_id', $this->filterBarangId))
            ->when($this->tanggalMulai, fn ($q) => $q->whereDate('dibuat_pada', '>=', $this->tanggalMulai))
            ->when($this->tanggalSelesai, fn ($q) => $q->whereDate('dibuat_pada', '<=', $this->tanggalSelesai))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('keterangan', 'ilike', "%{$this->search}%")
                        ->orWhere('referensi_jenis', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%")->orWhere('kode', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('stokBatch', fn ($sb) => $sb->where('nomor_batch', 'ilike', "%{$this->search}%"));
                });
            });

        $items = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif', 60, fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());
        $daftarBarang = Cache::remember('dropdown_barang_aktif', 60, fn () => Barang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.persediaan.buku-mutasi-index', [
            'items'        => $items,
            'daftarGudang' => $daftarGudang,
            'daftarBarang' => $daftarBarang,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Buku Mutasi & Kartu Stok']);
    }
}
