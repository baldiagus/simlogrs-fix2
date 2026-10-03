<?php

namespace App\Livewire\Master;

use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\KategoriBarang;
use Livewire\Attributes\Validate;
use Livewire\Component;

class KategoriBarangIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal;

    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showDetailModal = false;
    public ?KategoriBarang $selectedItem = null;

    #[Validate('required|string|max:50')]
    public string $kode = '';

    #[Validate('required|string|max:100')]
    public string $nama = '';

    #[Validate('nullable|string')]
    public ?string $deskripsi = null;

    #[Validate('required|in:AKTIF,NONAKTIF')]
    public string $status = 'AKTIF';

    public function create()
    {
        abort_unless(auth()->user()?->can('create_master::kategori::barang'), 403);

        $this->reset(['editingId', 'kode', 'nama', 'deskripsi', 'status']);
        $this->status = 'AKTIF';
        $this->showModal = true;
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::kategori::barang'), 403);

        $kat = KategoriBarang::findOrFail($id);
        $this->editingId = $kat->id;
        $this->kode = $kat->kode;
        $this->nama = $kat->nama;
        $this->deskripsi = $kat->deskripsi;
        $this->status = $kat->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'kode' => 'required|string|max:50|unique:kategori_barang,kode,' . ($this->editingId ?? 'NULL') . ',id',
            'nama' => 'required|string|min:2|max:100',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ], [
            'kode.required' => 'Kode kategori barang wajib diisi.',
            'kode.unique'   => 'Kode kategori sudah terdaftar.',
            'nama.required' => 'Nama kategori barang wajib diisi.',
            'status.required' => 'Status kategori wajib ditentukan.',
        ]);

        $data = [
            'kode' => strtoupper($this->kode),
            'nama' => $this->nama,
            'deskripsi' => $this->deskripsi,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            abort_unless(auth()->user()?->can('update_master::kategori::barang'), 403);
            $kat = KategoriBarang::findOrFail($this->editingId);
            $kat->update($data);
            $this->toast("Kategori '{$kat->nama}' berhasil diperbarui.", 'success');
        } else {
            abort_unless(auth()->user()?->can('create_master::kategori::barang'), 403);
            KategoriBarang::create($data);
            $this->toast("Kategori baru berhasil ditambahkan.", 'success');
        }

        $this->showModal = false;
    }
    public function confirmToggleStatus(int $id): void
    {
        $kat = KategoriBarang::findOrFail($id);
        $nextStatus = $kat->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $variant = $nextStatus === 'NONAKTIF' ? 'danger' : 'primary';

        $this->askConfirmation(
            target: $id,
            action: 'toggleStatus',
            title: 'Konfirmasi Ubah Status Kategori',
            description: "Ubah status kategori '{$kat->nama}' ({$kat->kode}) menjadi {$nextStatus}?",
            buttonText: "Ya, Ubah ke {$nextStatus}",
            variant: $variant
        );
    }

    public function toggleStatus(int $id)
    {
        abort_unless(auth()->user()?->can('update_master::kategori::barang'), 403);

        $kat = KategoriBarang::findOrFail($id);
        $newStatus = $kat->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $kat->update(['status' => $newStatus]);

        $this->toast("Status kategori '{$kat->nama}' diubah menjadi {$newStatus}.", 'info');
    }

    public function confirmDelete(int $id): void
    {
        $kat = KategoriBarang::findOrFail($id);

        $this->askConfirmation(
            target: $id,
            action: 'delete',
            title: 'Konfirmasi Hapus Kategori',
            description: "Apakah Anda yakin ingin menghapus kategori '{$kat->nama}' ({$kat->kode})? Tindakan ini tidak dapat dibatalkan.",
            buttonText: 'Ya, Hapus Data',
            variant: 'danger'
        );
    }

    public function delete(int $id)
    {
        abort_unless(auth()->user()?->can('delete_master::kategori::barang') || auth()->user()?->hasRole('super_admin'), 403, 'Anda tidak memiliki hak akses menghapus kategori barang.');

        $kat = KategoriBarang::findOrFail($id);

        if ($kat->barang()->exists()) {
            $this->toast("Kategori '{$kat->nama}' tidak dapat dihapus karena masih digunakan oleh data barang. Silakan nonaktifkan status kategori.", 'danger');
            return;
        }

        $namaKat = $kat->nama;
        $kat->delete();

        $this->toast("Kategori '{$namaKat}' berhasil dihapus.", 'success');
    }

    public function showDetail(int $id): void
    {
        $this->selectedItem = KategoriBarang::withCount('barang')->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('view_any_master::kategori::barang') || auth()->user()?->can('view_master::kategori::barang'), 403);

        $items = KategoriBarang::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama', 'ilike', "%{$this->search}%")
                        ->orWhere('kode', 'ilike', "%{$this->search}%");
                });
            })
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);

        return view('livewire.master.kategori-barang-index', [
            'items' => $items,
            'editingId' => $this->editingId,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ])->layout('layouts.portal', ['portal' => $this->getPortalName(), 'title' => 'Kategori Komoditas']);
    }
}
