<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Master\Gudang;
use App\Models\Master\LokasiGudang;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LokasiGudangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?\App\Models\Master\LokasiGudang $selectedItem = null;
    public ?int $filterGudangId = null;

    #[Validate('required|exists:gudang,id')]
    public ?int $gudang_id = null;

    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:100')]
    public string $nama = '';

    #[Validate('nullable|string|max:20')]
    public ?string $baris = null;

    #[Validate('nullable|string|max:20')]
    public ?string $kolom = null;

    #[Validate('nullable|string|max:20')]
    public ?string $level = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function showDetail(int $id): void
    {
        $this->selectedItem = LokasiGudang::with('gudang')->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::lokasi::gudang'), 403);

        $this->reset(['editingId', 'gudang_id', 'kode', 'nama', 'baris', 'kolom', 'level', 'status']);
        $this->gudang_id = $this->filterGudangId ?? Gudang::where('status', 'AKTIF')->value('id');
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::lokasi::gudang'), 403);

        $lokasi = LokasiGudang::findOrFail($id);
        $this->editingId = $lokasi->id;
        $this->gudang_id = $lokasi->gudang_id;
        $this->kode = $lokasi->kode;
        $this->nama = $lokasi->nama;
        $this->baris = $lokasi->baris;
        $this->kolom = $lokasi->kolom;
        $this->level = $lokasi->level;
        $this->status = $lokasi->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'gudang_id' => 'required|exists:gudang,id',
            'kode' => 'required|string|max:50',
            'nama' => 'required|string|min:2|max:100',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ], [
            'gudang_id.required' => 'Fasilitas gudang wajib dipilih.',
            'kode.required'      => 'Kode rak / bin wajib diisi.',
            'nama.required'      => 'Nama rak penyimpanan wajib diisi.',
            'status.required'    => 'Status rak wajib ditentukan.',
        ]);

        // Cek keunikan kode per gudang
        $exists = LokasiGudang::where('gudang_id', $this->gudang_id)
            ->where('kode', strtoupper($this->kode))
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($exists) {
            $this->addError('kode', 'Kode rak/lokasi ini sudah terdaftar di gudang yang dipilih.');
            return;
        }

        $data = [
            'gudang_id' => $this->gudang_id,
            'kode' => strtoupper($this->kode),
            'nama' => $this->nama,
            'baris' => $this->baris,
            'kolom' => $this->kolom,
            'level' => $this->level,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::lokasi::gudang'), 403);
            $lokasi = LokasiGudang::findOrFail($this->editingId);
            $lokasi->update($data);
            $this->toast("Lokasi rak '{$lokasi->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::lokasi::gudang'), 403);
            LokasiGudang::create($data);
            $this->toast("Lokasi rak baru berhasil ditambahkan.", 'success');
        }

        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $lokasi = LokasiGudang::findOrFail($id);
        $nextStatus = $lokasi->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Rak / Bin',
            description: "Ubah status rak '{$lokasi->nama}' ({$lokasi->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::lokasi::gudang'), 403);

        $lokasi = LokasiGudang::findOrFail($id);
        $newStatus = $lokasi->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $lokasi->update(['status' => $newStatus]);

        $this->toast("Status rak '{$lokasi->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmDelete(int $id): void
    {
        $lokasi = LokasiGudang::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'delete',
            title: 'Konfirmasi Hapus Rak / Bin',
            description: "Apakah Anda yakin ingin menghapus rak '{$lokasi->nama}' ({$lokasi->kode})? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Data',
            variant: 'danger'
        );
    }

    public function delete(int $id)
    {
        abort_unless(auth()->user()?->can('delete_master::lokasi::gudang') || auth()->user()?->hasRole('super_admin'), 403, 'Anda tidak memiliki hak akses menghapus rak gudang.');

        $lokasi = LokasiGudang::findOrFail($id);

        $hasDependencies = \App\Models\Persediaan\StokBatch::where('lokasi_gudang_id', $id)->exists()
            || \App\Models\Pengadaan\PenerimaanBarangDetail::where('lokasi_gudang_id', $id)->exists()
            || \App\Models\Aset\Aset::where('lokasi_gudang_id', $id)->exists();

        if ($hasDependencies) {
            $this->toast("Rak '{$lokasi->nama}' tidak dapat dihapus karena masih memuat stok fisik atau riwayat penerimaan barang. Silakan nonaktifkan status rak.", 'danger');
            return;
        }

        $namaRak = $lokasi->nama;
        $lokasi->delete();

        $this->toast("Rak '{$namaRak}' berhasil dihapus.", 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::lokasi::gudang') || auth()->user()?->can('view_master::lokasi::gudang'), 403);

        $daftarGudang = Gudang::where('status', 'AKTIF')->orderBy('nama')->get(['id', 'nama', 'kode']);

        $items = LokasiGudang::with('gudang')
            ->when($this->filterGudangId, fn ($q) => $q->where('gudang_id', $this->filterGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.lokasi-gudang-index', [
            'items' => $items,
            'daftarGudang' => $daftarGudang,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Tata Letak Rak & Bin']);
    }
}
