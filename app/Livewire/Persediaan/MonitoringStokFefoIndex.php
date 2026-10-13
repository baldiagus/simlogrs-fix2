<?php

namespace App\Livewire\Persediaan;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Persediaan\StokBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;

class MonitoringStokFefoIndex extends Component
{
    use HasTableFilters, HasPortalLayout;

    #[Url(history: true)]
    public string $filterKategoriKedaluwarsa = 'KRITIS'; // 'SEMUA', 'KRITIS', 'PERINGATAN', 'KEDALUWARSA', 'ROP'

    #[Url(history: true)]
    public ?int $filterGudangId = null;

    public bool $showDetailModal = false;
    public ?StokBatch $selectedBatch = null;

    public function showDetail(int $id): void
    {
        $this->selectedBatch = StokBatch::with(['barang.kategori', 'barang.satuan', 'gudang', 'lokasiGudang'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function setFilter(string $kategori): void
    {
        $this->filterKategoriKedaluwarsa = $kategori;
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('view_any_gudang_stok::monitoring::fefo') ||
            auth()->user()?->can('view_gudang_stok::monitoring::fefo'),
            403
        );

        $today = now()->toDateString();
        $date30 = now()->addDays(30)->toDateString();
        $date90 = now()->addDays(90)->toDateString();

        // 1. Hitung Ringkasan Metrik
        $countExpired = StokBatch::where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<', $today)
            ->count();

        $countKritis30 = StokBatch::where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->whereBetween('tanggal_kedaluwarsa', [$today, $date30])
            ->count();

        $countPeringatan90 = StokBatch::where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->whereBetween('tanggal_kedaluwarsa', [$date30, $date90])
            ->count();

        $countRop = Barang::where('status', 'AKTIF')
            ->where('titik_pesan', '>', 0)
            ->whereHas('stokBatch', function ($q) {
                $q->where('status', 'AKTIF');
            }, '<=', 0)
            ->orWhereRaw('(SELECT COALESCE(SUM(jumlah), 0) FROM stok_batch WHERE stok_batch.barang_id = barang.id AND stok_batch.status = \'AKTIF\') <= barang.titik_pesan')
            ->where('titik_pesan', '>', 0)
            ->count();

        $nilaiTertahanKritis = (float) StokBatch::where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where('tanggal_kedaluwarsa', '<=', $date90)
            ->selectRaw('COALESCE(SUM(jumlah * harga_satuan), 0) as total')
            ->value('total');

        // 2. Query Data Batch FEFO
        $query = StokBatch::with(['barang.satuan', 'gudang', 'lokasiGudang'])
            ->where('status', 'AKTIF')
            ->where('jumlah', '>', 0)
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nomor_batch', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%")->orWhere('kode', 'ilike', "%{$this->search}%"));
                });
            });

        if ($this->filterKategoriKedaluwarsa === 'KRITIS') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereBetween('tanggal_kedaluwarsa', [$today, $date30]);
        } elseif ($this->filterKategoriKedaluwarsa === 'PERINGATAN') {
            $query->whereNotNull('tanggal_kedaluwarsa')->whereBetween('tanggal_kedaluwarsa', [$date30, $date90]);
        } elseif ($this->filterKategoriKedaluwarsa === 'KEDALUWARSA') {
            $query->whereNotNull('tanggal_kedaluwarsa')->where('tanggal_kedaluwarsa', '<', $today);
        }

        $items = $query->orderByRaw('tanggal_kedaluwarsa ASC NULLS LAST')
            ->paginate($this->perPage);

        $daftarGudang = Cache::remember('dropdown_gudang_aktif', config('simlog.cache.dropdown_ttl'), fn () => Gudang::where('status', 'AKTIF')->orderBy('nama')->get());

        return view('livewire.persediaan.monitoring-stok-fefo-index', [
            'items'                => $items,
            'daftarGudang'         => $daftarGudang,
            'countExpired'         => $countExpired,
            'countKritis30'        => $countKritis30,
            'countPeringatan90'    => $countPeringatan90,
            'countRop'             => $countRop,
            'nilaiTertahanKritis'  => $nilaiTertahanKritis,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Monitoring Kedaluwarsa FEFO & Buffer']);
    }
}
