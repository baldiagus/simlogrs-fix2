<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\MerekBarang;
use App\Models\Pemasok;
use App\Models\SatuanBarang;
use App\Support\Generator\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout;

    public bool $showModal = false;
    public ?int $editingId = null;

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

    #[Validate('required|integer|min:0')]
    public int $stok_minimum = 0;

    #[Validate('required|integer|min:0')]
    public int $stok_maksimum = 0;

    #[Validate('required|integer|min:0')]
    public int $titik_pesan = 0;

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

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::barang'), 403);

        $this->reset([
            'editingId',
            'kategori_id',
            'satuan_id',
            'merek_id',
            'pemasok_default_id',
            'kode',
            'nama',
            'stok_minimum',
            'stok_maksimum',
            'titik_pesan',
            'harga_beli_terakhir',
            'harga_estimasi',
        ]);

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
        $this->stok_minimum = $barang->stok_minimum;
        $this->stok_maksimum = $barang->stok_maksimum;
        $this->titik_pesan = $barang->titik_pesan;
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
            'stok_minimum' => 'required|integer|min:0',
            'stok_maksimum' => 'required|integer|min:0',
            'titik_pesan' => 'required|integer|min:0',
            'harga_beli_terakhir' => 'required|numeric|min:0',
            'harga_estimasi' => 'required|numeric|min:0',
            'status'      => 'required|in:AKTIF,NONAKTIF',
        ]);

        $kodeFinal = ! empty($this->kode)
            ? strtoupper(trim($this->kode))
            : CodeGenerator::generate('BRG', Barang::class);

        $data = [
            'kategori_id' => $this->kategori_id,
            'satuan_id'   => $this->satuan_id,
            'merek_id'    => $this->merek_id,
            'pemasok_default_id' => $this->pemasok_default_id,
            'kode'        => $kodeFinal,
            'nama'        => $this->nama,
            'tipe'        => $this->tipe,
            'stok_minimum' => $this->stok_minimum,
            'stok_maksimum' => $this->stok_maksimum,
            'titik_pesan' => $this->titik_pesan,
            'harga_beli_terakhir' => $this->harga_beli_terakhir,
            'harga_estimasi' => $this->harga_estimasi,
            'status'      => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::barang'), 403);
            $barang = Barang::findOrFail($this->editingId);
            $barang->update($data);
            $this->toast("Barang '{$barang->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::barang'), 403);
            Barang::create($data);
            $this->toast("Barang '{$this->nama}' berhasil didaftarkan.", 'success');
        }

        $this->showModal = false;
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

        $daftarKategori = KategoriBarang::where('status', 'AKTIF')->get();
        $daftarSatuan = SatuanBarang::where('status', 'AKTIF')->get();
        $daftarMerek = MerekBarang::where('status', 'AKTIF')->get();
        $daftarPemasok = Pemasok::where('status', 'AKTIF')->get();

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
            ->orderBy('id', 'desc')
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
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Katalog Master Barang']);
    }
}
