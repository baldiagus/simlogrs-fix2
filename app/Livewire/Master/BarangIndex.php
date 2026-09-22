<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\MerekBarang;
use App\Models\Pemasok;
use App\Models\SatuanBarang;
use App\Support\Generator\CodeGenerator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?\App\Models\Barang $selectedBarang = null;

    #[Url(history: true)]
    public string $filterTipe = '';

    #[Url(history: true)]
    public ?int $filterKategoriId = null;

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Validate('required|exists:kategori_barang,id')]
    public ?int $kategori_id = null;

    #[Validate('required|exists:satuan_barang,id')]
    public ?int $satuan_id = null;

    #[Validate('nullable|exists:merek_barang,id')]
    public ?int $merek_id = null;

    #[Validate('nullable|exists:pemasok,id')]
    public ?int $pemasok_default_id = null;

    #[Validate('nullable|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:255')]
    public string $nama = '';

    #[Validate('required|in:HABIS_PAKAI,ASET,SPAREPART')]
    public string $tipe = 'HABIS_PAKAI';

    #[Validate('nullable|integer|min:0')]
    public ?int $stok_minimum = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $stok_maksimum = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $titik_pesan = null;

    #[Validate('required|numeric|min:0')]
    public float $harga_beli_terakhir = 0;

    #[Validate('required|numeric|min:0')]
    public float $harga_estimasi = 0;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function updatedFilterTipe(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKategoriId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function showDetail(int $id): void
    {
        $this->selectedBarang = Barang::with(['kategori', 'satuan', 'merek', 'pemasokDefault'])->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::barang'), 403);

        $this->reset([
            'editingId',
            'kategori_id',
            'satuan_id',
            'merek_id',
            'pemasok_default_id',
            'nama',
            'stok_minimum',
            'stok_maksimum',
            'titik_pesan',
            'harga_beli_terakhir',
            'harga_estimasi',
        ]);

        $this->kode = CodeGenerator::generate('BRG', Barang::class);
        $this->kategori_id = KategoriBarang::where('status', 'AKTIF')->value('id');
        $this->satuan_id = SatuanBarang::where('status', 'AKTIF')->value('id');
        $this->tipe = 'HABIS_PAKAI';
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::barang'), 403);

        $barang = Barang::findOrFail($id);
        $this->editingId = $barang->id;
        $this->kategori_id = $barang->kategori_id;
        $this->satuan_id = $barang->satuan_id;
        $this->merek_id = $barang->merek_id;
        $this->pemasok_default_id = $barang->pemasok_default_id;
        $this->kode = $barang->kode;
        $this->nama = $barang->nama;
        $this->tipe = $barang->tipe;
        $this->stok_minimum = $barang->stok_minimum ?: null;
        $this->stok_maksimum = $barang->stok_maksimum ?: null;
        $this->titik_pesan = $barang->titik_pesan ?: null;
        $this->harga_beli_terakhir = (float) $barang->harga_beli_terakhir;
        $this->harga_estimasi = (float) $barang->harga_estimasi;
        $this->status = $barang->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kategori_id' => 'required|exists:kategori_barang,id',
            'satuan_id'   => 'required|exists:satuan_barang,id',
            'merek_id'    => 'nullable|exists:merek_barang,id',
            'pemasok_default_id' => 'nullable|exists:pemasok,id',
            'kode'        => 'nullable|string|max:50|unique:barang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama'        => 'required|string|max:255',
            'tipe'        => 'required|in:HABIS_PAKAI,ASET,SPAREPART',
            'stok_minimum' => 'nullable|integer|min:0',
            'stok_maksimum' => 'nullable|integer|min:0',
            'titik_pesan' => 'nullable|integer|min:0',
            'harga_beli_terakhir' => 'required|numeric|min:0',
            'harga_estimasi' => 'required|numeric|min:0',
            'status'      => 'required|in:AKTIF,NONAKTIF',
        ]);

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::barang'), 403);
            $barang = Barang::findOrFail($this->editingId);
            $barang->update([
                'kategori_id' => $this->kategori_id,
                'satuan_id'   => $this->satuan_id,
                'merek_id'    => $this->merek_id,
                'pemasok_default_id' => $this->pemasok_default_id,
                'nama'        => $this->nama,
                'tipe'        => $this->tipe,
                'stok_minimum' => $this->stok_minimum ?? 0,
                'stok_maksimum' => $this->stok_maksimum ?? 0,
                'titik_pesan' => $this->titik_pesan ?? 0,
                'harga_beli_terakhir' => $this->harga_beli_terakhir,
                'harga_estimasi' => $this->harga_estimasi,
                'status'      => $this->status,
            ]);
            $this->toast("Barang '{$barang->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::barang'), 403);
            $kodeFinal = $this->kode ?: CodeGenerator::generate('BRG', Barang::class);
            Barang::create([
                'kategori_id' => $this->kategori_id,
                'satuan_id'   => $this->satuan_id,
                'merek_id'    => $this->merek_id,
                'pemasok_default_id' => $this->pemasok_default_id,
                'kode'        => $kodeFinal,
                'nama'        => $this->nama,
                'tipe'        => $this->tipe,
                'stok_minimum' => $this->stok_minimum ?? 0,
                'stok_maksimum' => $this->stok_maksimum ?? 0,
                'titik_pesan' => $this->titik_pesan ?? 0,
                'harga_beli_terakhir' => $this->harga_beli_terakhir,
                'harga_estimasi' => $this->harga_estimasi,
                'status'      => $this->status,
            ]);
            $this->toast("Barang '{$this->nama}' ({$kodeFinal}) berhasil didaftarkan.", 'success');
        }

        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $barang = Barang::findOrFail($id);
        $nextStatus = $barang->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Barang',
            description: "Ubah status komoditas '{$barang->nama}' ({$barang->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::barang'), 403);

        $barang = Barang::findOrFail($id);
        $newStatus = $barang->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $barang->update(['status' => $newStatus]);

        $this->toast("Status barang '{$barang->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::barang') || auth()->user()?->can('view_master::barang'), 403);

        $daftarKategori = Cache::remember('dropdown_kategori_aktif', 60, fn () => KategoriBarang::where('status', 'AKTIF')->get());
        $daftarSatuan = Cache::remember('dropdown_satuan_aktif', 60, fn () => SatuanBarang::where('status', 'AKTIF')->get());
        $daftarMerek = Cache::remember('dropdown_merek_aktif', 60, fn () => MerekBarang::where('status', 'AKTIF')->get());
        $daftarPemasok = Cache::remember('dropdown_pemasok_aktif', 60, fn () => Pemasok::where('status', 'AKTIF')->get());

        $items = Barang::with(['kategori', 'satuan', 'merek', 'pemasokDefault'])
            ->when($this->filterTipe, fn ($q) => $q->where('tipe', $this->filterTipe))
            ->when($this->filterKategoriId, fn ($q) => $q->where('kategori_id', $this->filterKategoriId))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                // Memanfaatkan PostgreSQL GIN Trigram index pada kolom nama
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.barang-index', [
            'items' => $items,
            'daftarKategori' => $daftarKategori,
            'daftarSatuan' => $daftarSatuan,
            'daftarMerek' => $daftarMerek,
            'daftarPemasok' => $daftarPemasok,
            'editingId' => $this->editingId,
            'filterTipe' => $this->filterTipe,
            'filterKategoriId' => $this->filterKategoriId,
            'filterStatus' => $this->filterStatus,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Katalog Master Barang']);
    }
}
